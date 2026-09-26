<?php

namespace Modules\Core\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Modules\Core\Notifications\MemberJoined;
use Modules\Core\Notifications\MemberRemoved;
use Modules\Core\Notifications\PasswordChangedAlert;
use Modules\Core\Notifications\RolesChanged;
use Modules\DataPlatform\Events\DomainEvent;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index()
    {
        return Role::query()->where('team_id', tenant()->getTenantKey())
            ->with('permissions:id,name')->get(['id', 'name']);
    }

    public function users()
    {
        $userIds = DB::table('model_has_roles')
            ->where('team_id', tenant()->getTenantKey())
            ->where('model_type', User::class)
            ->pluck('model_id');

        return User::whereIn('id', $userIds)->orderBy('name')->get(['id', 'name', 'email', 'last_login_at', 'last_login_ip'])
            ->map(fn (User $u) => $u->setAttribute('role_names', $u->getRoleNames()));
    }

    public function storeRole(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_-]*$/'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        abort_if(
            Role::where('team_id', tenant()->getTenantKey())->where('name', $validated['name'])->exists(),
            422,
            'Rolle existiert bereits.'
        );

        $this->guardPermissionSet($request, $validated['permissions'] ?? [], 'Rolle');

        $role = new Role;
        $role->name = $validated['name'];
        $role->guard_name = 'web';
        $role->team_id = tenant()->getTenantKey();
        $role->save();
        $role->syncPermissions($validated['permissions'] ?? []);

        $this->recordRoleEvent('created', $role);

        return response()->json([
            'id' => $role->id,
            'name' => $role->name,
            'permissions' => $role->permissions()->orderBy('name')->pluck('name'),
        ], 201);
    }

    public function destroyRole(Request $request, Role $role)
    {
        abort_if($role->team_id !== tenant()->getTenantKey(), 404);
        abort_if(in_array($role->name, ['holding', 'administrator']), 422, 'System-Rolle kann nicht gelöscht werden.');
        $this->guardRoleWithinOwnPermissions($request, $role);
        if ($role->permissions->contains('name', 'roles.manage')) {
            $this->guardOtherRolesManager($role, 'Letzte roles.manage-Rolle kann nicht gelöscht werden.');
        }

        $role->delete();

        $this->recordRoleEvent('deleted', $role);

        return response()->noContent();
    }

    public function permissions()
    {
        return Permission::orderBy('name')->pluck('name');
    }

    public function updateRole(Request $request, Role $role)
    {
        abort_if($role->team_id !== tenant()->getTenantKey(), 404);

        $validated = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $this->guardRoleWithinOwnPermissions($request, $role);
        $this->guardPermissionSet($request, $validated['permissions'], 'Rolle');
        if ($role->permissions->contains('name', 'roles.manage')
            && ! in_array('roles.manage', $validated['permissions'], true)) {
            $this->guardOtherRolesManager($role, 'Letzte roles.manage-Rolle kann nicht ihrer Rechte beraubt werden.');
        }
        $role->syncPermissions($validated['permissions']);

        $this->recordRoleEvent('permissions_updated', $role, ['permissions' => $validated['permissions']]);

        return response()->json([
            'id' => $role->id,
            'name' => $role->name,
            'permissions' => $role->permissions()->orderBy('name')->pluck('name'),
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'last_login_at' => $user->last_login_at,
            'last_login_ip' => $user->last_login_ip,
            'email_verified' => $user->email_verified_at !== null,
            'created_at' => $user->created_at,
            'tokens_count' => $user->tokens()->count(),
            'password_changed_at' => $user->password_changed_at,
            'tenants' => $this->memberships($user),
            'muted_kinds' => $user->notification_muted ?? [],
        ]);
    }

    private function memberships(User $user): array
    {
        $rows = DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->where('model_id', $user->id)
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->select('model_has_roles.team_id', 'roles.name')
            ->get()
            ->groupBy('team_id');

        if ($rows->isEmpty()) {
            return [];
        }

        $tenants = Tenant::whereIn('id', $rows->keys())->get()->keyBy('id');

        return $rows->map(fn ($roles, $teamId) => [
            'id' => $teamId,
            'name' => $tenants->get($teamId)?->name,
            'roles' => $roles->pluck('name')->values()->all(),
        ])->values()->all();
    }

    public function updateMe(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $emailChanged = array_key_exists('email', $validated)
            && $validated['email'] !== $user->email;
        $user->update($validated);
        if ($emailChanged) {
            $user->forceFill(['email_verified_at' => null])->save();
        }
        $this->recordMemberEvent('updated', $user);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);
    }

    public function notificationPrefs(Request $request)
    {
        return response()->json(['muted_kinds' => $request->user()->notification_muted ?? []]);
    }

    public function updateNotificationPrefs(Request $request)
    {
        $data = $request->validate([
            'muted_kinds' => 'required|array|max:100',
            'muted_kinds.*' => 'string|max:100',
        ]);
        $request->user()->forceFill(['notification_muted' => array_values($data['muted_kinds'])])->save();

        return response()->json(['muted_kinds' => array_values($data['muted_kinds'])]);
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $request->user();
        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json(['message' => 'Aktuelles Passwort ist falsch.'], 422);
        }

        $user->update(['password' => Hash::make($validated['password']), 'password_changed_at' => now()]);
        $user->tokens()->delete();
        $this->recordMemberEvent('password_changed', $user);
        $user->notify(new PasswordChangedAlert('Profil'));

        return response()->json(['message' => 'Passwort geändert — alle API-Token wurden widerrufen.']);
    }

    public function userRoles(User $user)
    {
        return response()->json([
            'user_id' => $user->id,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'password' => ['nullable', 'string', 'min:8'],
            'roles' => ['nullable', 'array'],
            'roles.*' => [
                'string',
                Rule::exists('roles', 'name')->where('team_id', tenant()->getTenantKey()),
            ],
        ]);

        $roles = $validated['roles'] ?? ['mitarbeiter'];
        $this->guardAssignableRoles($request, $roles);
        $user = User::where('email', $validated['email'])->first();
        if ($user && $user->hasPermissionTo('roles.manage') && ! $this->rolesGrantManage($roles)) {
            $this->guardLastRolesManager($user);
        }
        $initialPassword = null;
        $created = false;

        if (! $user) {
            $initialPassword = $validated['password'] ?? Str::password(16);
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($initialPassword),
            ]);
            $created = true;
        }

        $user->syncRoles($roles);

        $this->recordMemberEvent($created ? 'added' : 'roles_updated', $user, ['roles' => $roles]);
        $user->notify(new RolesChanged($user, $created ? 'added' : 'roles_updated', $roles));

        if ($created) {
            $this->notifyMemberJoined($request->user(), $user);
        }

        $payload = $this->userRoles($user)->getData(true);
        if ($initialPassword && ! isset($validated['password'])) {
            $payload['initial_password'] = $initialPassword;
        }

        return response()->json($payload + ['user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email]], 201);
    }

    public function assign(Request $request, User $user)
    {
        $validated = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [
                'string',
                Rule::exists('roles', 'name')->where('team_id', tenant()->getTenantKey()),
            ],
        ]);

        $this->guardAssignableRoles($request, $validated['roles']);
        if ($user->hasPermissionTo('roles.manage') && ! $this->rolesGrantManage($validated['roles'])) {
            $this->guardLastRolesManager($user);
        }
        $user->syncRoles($validated['roles']);

        $this->recordMemberEvent('roles_updated', $user, ['roles' => $validated['roles']]);
        $user->notify(new RolesChanged($user, 'roles_updated', $validated['roles']));

        return $this->userRoles($user);
    }

    /**
     * Privilege-Escalation-Guard: eine Rolle ist nur zuweisbar, wenn ihre
     * Permissions eine Teilmenge der eigenen Permissions sind — sonst
     * koennte ein eingeschraenkter roles.manager sich selbst 'administrator'
     * geben.
     */
    private function guardAssignableRoles(Request $request, array $roleNames): void
    {
        $actorPermissions = $request->user()->getAllPermissions()->pluck('name');

        foreach ($roleNames as $name) {
            $role = Role::where('name', $name)
                ->where('team_id', tenant()->getTenantKey())
                ->first();
            if (! $role) {
                continue;
            }
            $missing = $role->permissions->pluck('name')->diff($actorPermissions);
            abort_if($missing->isNotEmpty(), 403,
                "Rolle '{$name}' uebersteigt eigene Berechtigungen (fehlend: {$missing->implode(', ')}).");
        }
    }

    /**
     * Lockout-Guard: das letzte Mitglied mit roles.manage darf weder
     * abgestuft noch entfernt werden — sonst ist der Tenant unverwaltbar.
     */
    private function guardLastRolesManager(User $target): void
    {
        $memberIds = DB::table('model_has_roles')
            ->where('team_id', tenant()->getTenantKey())
            ->where('model_type', User::class)
            ->pluck('model_id');

        $anotherManager = User::whereIn('id', $memberIds)
            ->whereKeyNot($target->id)
            ->get()
            ->contains(fn (User $u) => $u->hasPermissionTo('roles.manage'));

        abort_unless($anotherManager, 422,
            'Letztes Mitglied mit roles.manage kann nicht abgestuft oder entfernt werden.');
    }

    private function rolesGrantManage(array $roleNames): bool
    {
        return Role::where('team_id', tenant()->getTenantKey())
            ->whereIn('name', $roleNames)
            ->whereHas('permissions', fn ($q) => $q->where('name', 'roles.manage'))
            ->exists();
    }

    private function guardOtherRolesManager(Role $role, string $message): void
    {
        // Der Tenant darf durch die Mutation nicht ohne roles.manager bleiben.
        $keepsManager = DB::table('model_has_roles')->where('model_has_roles.team_id', tenant()->getTenantKey())
            ->where('model_has_roles.model_type', User::class)
            ->join('role_has_permissions', 'role_has_permissions.role_id', '=', 'model_has_roles.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('permissions.name', 'roles.manage')
            ->where('model_has_roles.role_id', '!=', $role->id)
            ->exists();
        abort_unless($keepsManager, 422, $message);
    }

    private function guardRoleWithinOwnPermissions(Request $request, Role $role): void
    {
        $this->guardPermissionSet($request, $role->permissions->pluck('name'),
            "Rolle '{$role->name}'");
    }

    private function guardPermissionSet(Request $request, iterable $permissions, string $context): void
    {
        $missing = collect($permissions)->diff($request->user()->getAllPermissions()->pluck('name'));
        abort_if($missing->isNotEmpty(), 403,
            "{$context} uebersteigt eigene Berechtigungen (fehlend: {$missing->implode(', ')}).");
    }

    public function leaveTenant(Request $request)
    {
        $user = $request->user();
        if ($user->hasPermissionTo('roles.manage')) {
            $this->guardLastRolesManager($user);
        }

        DB::table('model_has_roles')
            ->where('team_id', tenant()->getTenantKey())
            ->where('model_type', User::class)
            ->where('model_id', $user->id)
            ->delete();

        $this->recordMemberEvent('left', $user);
        $this->notifyMemberRemoved($user, $user);

        return response()->noContent();
    }

    public function deleteMe(Request $request)
    {
        $user = $request->user();
        $memberships = DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->where('model_id', $user->id)
            ->count();
        abort_if($memberships > 0, 422, 'Bitte zuerst alle Mandanten verlassen.');

        $user->tokens()->delete();
        $user->delete();

        return response()->noContent();
    }

    public function remove(Request $request, User $user)
    {
        abort_if($request->user()->is($user), 422, 'Eigenes Mitglied kann nicht entfernt werden.');
        if ($user->hasPermissionTo('roles.manage')) {
            $this->guardLastRolesManager($user);
        }

        DB::table('model_has_roles')
            ->where('team_id', tenant()->getTenantKey())
            ->where('model_type', User::class)
            ->where('model_id', $user->id)
            ->delete();

        $this->recordMemberEvent('removed', $user);
        $user->notify(new RolesChanged($user, 'removed'));
        $this->notifyMemberRemoved($request->user(), $user);

        return response()->noContent();
    }

    private function notifyMemberJoined(User $actor, User $member): void
    {
        $memberIds = DB::table('model_has_roles')
            ->where('team_id', tenant()->getTenantKey())
            ->where('model_type', User::class)
            ->pluck('model_id');

        User::whereIn('id', $memberIds)
            ->whereNotIn('id', [$actor->id, $member->id])
            ->get()
            ->filter(fn (User $u) => $u->hasPermissionTo('roles.manage'))
            ->each(fn (User $u) => $u->notify(new MemberJoined($member)));
    }

    private function notifyMemberRemoved(User $actor, User $member): void
    {
        $memberIds = DB::table('model_has_roles')
            ->where('team_id', tenant()->getTenantKey())
            ->where('model_type', User::class)
            ->pluck('model_id');

        User::whereIn('id', $memberIds)
            ->whereNotIn('id', [$actor->id, $member->id])
            ->get()
            ->filter(fn (User $u) => $u->hasPermissionTo('roles.manage'))
            ->each(fn (User $u) => $u->notify(new MemberRemoved($member)));
    }

    private function recordMemberEvent(string $action, User $user, array $payload = []): void
    {
        $event = new DomainEvent(
            type: "user.{$action}",
            tenantId: (string) tenant()->getTenantKey(),
            subject: ['type' => 'user', 'id' => $user->id, 'title' => $user->name],
            payload: $payload + ['email' => $user->email],
        );
        $event->setMetaData(['tenant_id' => (string) tenant()->getTenantKey()]);

        event($event);
    }

    private function recordRoleEvent(string $action, Role $role, array $payload = []): void
    {
        $event = new DomainEvent(
            type: "role.{$action}",
            tenantId: (string) tenant()->getTenantKey(),
            subject: ['type' => 'role', 'id' => $role->id, 'title' => $role->name],
            payload: $payload,
        );
        $event->setMetaData(['tenant_id' => (string) tenant()->getTenantKey()]);

        event($event);
    }
}
