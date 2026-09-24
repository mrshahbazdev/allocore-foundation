<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Dokument C Rollenmodell: Holding, Geschaeftsfuehrer, Administrator,
 * Mitarbeiter, Berater, Auditor, Kunde — je Tenant (team) eigenstaendig.
 */
class RoleSeeder extends Seeder
{
    public const DOMAINS = ['companies', 'persons', 'documents', 'tasks', 'compliance', 'experts', 'finance', 'projects'];
public const EXTRA_PERMISSIONS = ['metrics.view', 'roles.manage'];

    public const ROLE_MATRIX = [
        'holding' => ['*'],
        'administrator' => ['*'],
        'geschaeftsfuehrer' => ['*', '-roles.manage'],
        'mitarbeiter' => [
            'tasks.view', 'tasks.manage',
            'documents.view', 'documents.manage',
            'persons.view',
            'projects.view',
        ],
        'berater' => [
            'tasks.view', 'tasks.manage',
            'documents.view', 'documents.manage',
            'compliance.view', 'compliance.manage',
            'experts.view', 'experts.manage',
            'persons.view', 'companies.view',
            'projects.view', 'projects.manage',
            'metrics.view',
        ],
        'auditor' => [
            'compliance.view',
            'documents.view',
            'tasks.view',
            'projects.view',
            'metrics.view',
        ],
        'kunde' => [
            'documents.view',
            'experts.view',
        ],
    ];

    public function run(): void
    {
        foreach (Tenant::all() as $tenant) {
            self::forTenant($tenant);
        }
    }

    public static function forTenant(Tenant $tenant): void
    {
        $teamId = $tenant->getTenantKey();
        app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);

        $permissions = collect(self::EXTRA_PERMISSIONS);
        foreach (self::DOMAINS as $domain) {
            $permissions->push("{$domain}.view", "{$domain}.manage");
        }

        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (self::ROLE_MATRIX as $role => $grants) {
            $roleModel = Role::findOrCreate($role, 'web');

            $granted = in_array('*', $grants, true)
                ? $permissions->reject(fn ($p) => in_array("-{$p}", $grants, true))->all()
                : $grants;

            $roleModel->syncPermissions($granted);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
