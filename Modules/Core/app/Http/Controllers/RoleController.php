<?php

namespace Modules\Core\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Core\Notifications\MemberJoined;
use Modules\Core\Notifications\MemberRemoved;
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

        return User::whereIn('id', $userIds)->orderBy('name')->get(['id', 'name', 'email', 'last_login_at'])
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
        ]);
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

    public function remove(Request $request, User $user)
    {
        abort_if($request->user()->is($user), 422, 'Eigenes Mitglied kann nicht entfernt werden.');

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
