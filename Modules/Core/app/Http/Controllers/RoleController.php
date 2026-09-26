<?php

namespace Modules\Core\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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

        return User::whereIn('id', $userIds)->orderBy('name')->get(['id', 'name', 'email'])
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

        $role = new Role;
        $role->name = $validated['name'];
        $role->guard_name = 'web';
        $role->team_id = tenant()->getTenantKey();
        $role->save();
        $role->syncPermissions($validated['permissions'] ?? []);

        return response()->json([
            'id' => $role->id,
            'name' => $role->name,
            'permissions' => $role->permissions()->orderBy('name')->pluck('name'),
        ], 201);
    }

    public function destroyRole(Role $role)
    {
        abort_if($role->team_id !== tenant()->getTenantKey(), 404);
        abort_if(in_array($role->name, ['holding', 'administrator']), 422, 'System-Rolle kann nicht gelöscht werden.');

        $role->delete();

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

        $role->syncPermissions($validated['permissions']);

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
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $roles = $validated['roles'] ?? ['mitarbeiter'];
        $user = User::where('email', $validated['email'])->first();
        $initialPassword = null;

        if (! $user) {
            $initialPassword = $validated['password'] ?? Str::password(16);
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($initialPassword),
            ]);
        }

        $user->syncRoles($roles);

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
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $user->syncRoles($validated['roles']);

        return $this->userRoles($user);
    }

    public function remove(Request $request, User $user)
    {
        abort_if($request->user()->is($user), 422, 'Eigenes Mitglied kann nicht entfernt werden.');

        DB::table('model_has_roles')
            ->where('team_id', tenant()->getTenantKey())
            ->where('model_type', User::class)
            ->where('model_id', $user->id)
            ->delete();

        return response()->noContent();
    }
}
