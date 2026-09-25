<?php

namespace Modules\Core\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
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

    public function userRoles(User $user)
    {
        return response()->json([
            'user_id' => $user->id,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);
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
