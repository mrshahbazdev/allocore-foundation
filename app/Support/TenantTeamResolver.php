<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Contracts\PermissionsTeamResolver;

/**
 * Maps spatie/laravel-permission's "team" context onto the ALLOCORE tenant.
 * Roles and permissions are scoped per Mandant: the team_foreign_key is the
 * tenant id (UUID), resolved from the current tenancy context.
 */
class TenantTeamResolver implements PermissionsTeamResolver
{
    protected int|string|null $teamId = null;

    public function getPermissionsTeamId(): int|string|null
    {
        return tenancy()->initialized ? tenant()->getTenantKey() : $this->teamId;
    }

    public function setPermissionsTeamId(int|string|Model|null $id): void
    {
        if ($id instanceof Model) {
            $id = $id->getKey();
        }

        $this->teamId = $id;
    }
}
