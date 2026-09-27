<?php

namespace Modules\Core\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Restricts user-reference fields (responsible_id, owner_id, assignee_id)
 * to members of the current tenant — otherwise a foreign user's id
 * validates and receives notifications containing record titles.
 */
trait ValidatesTenantMembers
{
    protected static function tenantMemberRule(): Exists
    {
        return Rule::exists('users', 'id')->where(function ($q) {
            $q->whereIn('id', DB::table('model_has_roles')
                ->where('team_id', tenant()->getTenantKey())
                ->where('model_type', User::class)
                ->select('model_id'));
        });
    }
}
