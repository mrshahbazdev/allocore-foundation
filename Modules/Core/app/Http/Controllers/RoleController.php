<?php

namespace Modules\Core\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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
}
