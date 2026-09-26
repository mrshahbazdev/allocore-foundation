<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_creation_seeds_rollenmodell(): void
    {
        $tenant = $this->postJson('/api/v1/tenants', ['name' => 'Rollen GmbH'])
            ->assertCreated()->json('id');

        $this->actingAsUser();

        $res = $this->getJson('/api/v1/roles', ['X-Tenant' => $tenant])->assertOk();

        $names = collect($res->json())->pluck('name')->sort()->values();
        $this->assertEquals(
            ['administrator', 'auditor', 'berater', 'geschaeftsfuehrer', 'holding', 'kunde', 'mitarbeiter'],
            $names->all(),
        );

        $holding = collect($res->json())->firstWhere('name', 'holding');
        $this->assertContains('companies.manage', collect($holding['permissions'])->pluck('name'));
    }

    public function test_admin_can_assign_roles_to_user(): void
    {
        $tenant = $this->postJson('/api/v1/tenants', ['name' => 'Assign GmbH'])->json('id');
        $admin = $this->actingAsUser();
        $target = User::factory()->create();

        tenancy()->initialize(Tenant::find($tenant));
        $admin->assignRole('administrator');

        $this->putJson("/api/v1/users/{$target->id}/roles", ['roles' => ['auditor']], ['X-Tenant' => $tenant])
            ->assertOk()->assertJsonPath('roles.0', 'auditor');

        $this->getJson("/api/v1/users/{$target->id}/roles", ['X-Tenant' => $tenant])
            ->assertOk()->assertJsonPath('roles.0', 'auditor')
            ->assertJson(fn ($json) => $json->where('roles.0', 'auditor')
                ->whereType('permissions', 'array')
                ->etc());

        $this->assertContains('compliance.view', $this->getJson("/api/v1/users/{$target->id}/roles", ['X-Tenant' => $tenant])->json('permissions'));
    }

    public function test_users_endpoint_returns_only_tenant_members(): void
    {
        $tenant = $this->postJson('/api/v1/tenants', ['name' => 'Members GmbH'])->json('id');
        $admin = $this->actingAsUser();
        $member = User::factory()->create();
        $outsider = User::factory()->create();

        tenancy()->initialize(Tenant::find($tenant));
        $admin->assignRole('administrator');
        $member->assignRole('mitarbeiter');

        $ids = collect($this->getJson('/api/v1/users', ['X-Tenant' => $tenant])->assertOk()->json())->pluck('id');

        $this->assertContains($admin->id, $ids);
        $this->assertContains($member->id, $ids);
        $this->assertNotContains($outsider->id, $ids);

        $memberRow = collect($this->getJson('/api/v1/users', ['X-Tenant' => $tenant])->json())->firstWhere('id', $member->id);
        $this->assertSame(['mitarbeiter'], $memberRow['role_names']);
    }

    public function test_admin_can_create_user_and_attach_existing(): void
    {
        $tenant = $this->postJson('/api/v1/tenants', ['name' => 'UserCreate GmbH'])->json('id');
        $admin = $this->actingAsUser();
        tenancy()->initialize(Tenant::find($tenant));
        $admin->assignRole('administrator');

        $res = $this->postJson('/api/v1/users', [
            'name' => 'Neu Mitarbeiter', 'email' => 'neu@example.test',
        ], ['X-Tenant' => $tenant])->assertCreated()->json();

        $this->assertArrayHasKey('initial_password', $res);
        $this->assertSame(['mitarbeiter'], $res['roles']);

        $existing = User::factory()->create();
        $res2 = $this->postJson('/api/v1/users', [
            'name' => 'Vorhanden', 'email' => $existing->email, 'roles' => ['auditor'],
        ], ['X-Tenant' => $tenant])->assertCreated()->json();

        $this->assertSame($existing->id, $res2['user_id']);
        $this->assertSame(['auditor'], $res2['roles']);
        $this->assertArrayNotHasKey('initial_password', $res2);

        $memberless = $this->actingAsUser();
        $this->postJson('/api/v1/users', ['name' => 'X', 'email' => 'x@x.test'], ['X-Tenant' => $tenant])
            ->assertForbidden();
    }

    public function test_roles_validation_scoped_to_tenant(): void
    {
        $tenantA = $this->postJson('/api/v1/tenants', ['name' => 'ScopeA GmbH'])->json('id');
        $tenantB = $this->postJson('/api/v1/tenants', ['name' => 'ScopeB GmbH'])->json('id');
        $admin = $this->actingAsUser();

        tenancy()->initialize(Tenant::find($tenantB));
        $roleB = new Role;
        $roleB->name = 'spezialrolle';
        $roleB->guard_name = 'web';
        $roleB->team_id = $tenantB;
        $roleB->save();

        tenancy()->initialize(Tenant::find($tenantA));
        $admin->assignRole('administrator');

        $this->postJson('/api/v1/users', [
            'name' => 'N', 'email' => 'n@scope.test', 'roles' => ['spezialrolle'],
        ], ['X-Tenant' => $tenantA])->assertUnprocessable();

        $target = User::factory()->create();
        $this->putJson("/api/v1/users/{$target->id}/roles", ['roles' => ['spezialrolle']], ['X-Tenant' => $tenantA])
            ->assertUnprocessable();

        $this->postJson('/api/v1/users', [
            'name' => 'N', 'email' => 'n@scope.test', 'roles' => ['mitarbeiter'],
        ], ['X-Tenant' => $tenantA])->assertCreated();
    }

    public function test_roleless_user_cannot_assign_roles(): void
    {
        $tenant = $this->postJson('/api/v1/tenants', ['name' => 'NoPerm GmbH'])->json('id');
        $this->actingAsUser();
        $target = User::factory()->create();

        $this->putJson("/api/v1/users/{$target->id}/roles", ['roles' => ['auditor']], ['X-Tenant' => $tenant])
            ->assertForbidden();
    }

    public function test_me_returns_current_user_roles_and_permissions(): void
    {
        $tenant = $this->postJson('/api/v1/tenants', ['name' => 'Me GmbH'])->json('id');
        $user = $this->actingAsUser();

        tenancy()->initialize(Tenant::find($tenant));
        $user->assignRole('auditor');

        $res = $this->getJson('/api/v1/me', ['X-Tenant' => $tenant])->assertOk();
        $res->assertJsonPath('id', $user->id)
            ->assertJsonPath('email', $user->email)
            ->assertJsonPath('roles.0', 'auditor');
        $this->assertContains('compliance.view', $res->json('permissions'));
    }

    public function test_admin_can_remove_member_but_not_self(): void
    {
        $tenant = $this->postJson('/api/v1/tenants', ['name' => 'Remove GmbH'])->json('id');
        $admin = $this->actingAsUser();
        $target = User::factory()->create();

        tenancy()->initialize(Tenant::find($tenant));
        $admin->assignRole('administrator');
        $target->assignRole('mitarbeiter');

        $this->deleteJson("/api/v1/users/{$admin->id}", [], ['X-Tenant' => $tenant])
            ->assertStatus(422);

        $this->deleteJson("/api/v1/users/{$target->id}", [], ['X-Tenant' => $tenant])
            ->assertNoContent();

        $ids = collect($this->getJson('/api/v1/users', ['X-Tenant' => $tenant])->json())->pluck('id');
        $this->assertNotContains($target->id, $ids);
        $this->assertContains($admin->id, $ids);
    }

    public function test_role_update_requires_roles_manage_and_same_tenant(): void
    {
        $tenant = $this->postJson('/api/v1/tenants', ['name' => 'Guard GmbH'])->json('id');
        $other = $this->postJson('/api/v1/tenants', ['name' => 'Fremd GmbH'])->json('id');
        $user = $this->actingAsUser();

        tenancy()->initialize(Tenant::find($tenant));
        $user->assignRole('mitarbeiter');
        $role = Role::where('team_id', $tenant)->where('name', 'auditor')->firstOrFail();
        $foreignRole = Role::where('team_id', $other)->where('name', 'auditor')->firstOrFail();

        $this->putJson("/api/v1/roles/{$role->id}", ['permissions' => ['tasks.view']], ['X-Tenant' => $tenant])
            ->assertForbidden();

        tenancy()->end();
        $admin = User::factory()->create();
        tenancy()->initialize(Tenant::find($tenant));
        $admin->assignRole('administrator');
        tenancy()->end();
        Sanctum::actingAs($admin);

        $this->putJson("/api/v1/roles/{$foreignRole->id}", ['permissions' => ['tasks.view']], ['X-Tenant' => $tenant])
            ->assertNotFound();
    }

    public function test_admin_can_update_role_permissions(): void
    {
        $tenant = $this->postJson('/api/v1/tenants', ['name' => 'PermsEdit GmbH'])->json('id');
        $admin = $this->actingAsUser();

        tenancy()->initialize(Tenant::find($tenant));
        $admin->assignRole('administrator');

        $perms = $this->getJson('/api/v1/permissions', ['X-Tenant' => $tenant])->assertOk()->json();
        $this->assertContains('tasks.view', $perms);

        $role = Role::where('team_id', $tenant)->where('name', 'mitarbeiter')->firstOrFail();

        $this->putJson("/api/v1/roles/{$role->id}", ['permissions' => ['tasks.view']], ['X-Tenant' => $tenant])
            ->assertOk()->assertJsonPath('permissions.0', 'tasks.view');

        $this->assertEquals(['tasks.view'], $role->fresh()->permissions->pluck('name')->all());
    }

    public function test_admin_can_create_and_delete_custom_role(): void
    {
        $tenant = $this->postJson('/api/v1/tenants', ['name' => 'RoleCrud GmbH'])->json('id');
        $admin = $this->actingAsUser();

        tenancy()->initialize(Tenant::find($tenant));
        $admin->assignRole('administrator');

        $roleId = $this->postJson('/api/v1/roles', [
            'name' => 'buchhaltung', 'permissions' => ['finance.view', 'finance.manage'],
        ], ['X-Tenant' => $tenant])->assertCreated()->json('id');

        $this->assertDatabaseHas('roles', ['id' => $roleId, 'name' => 'buchhaltung', 'team_id' => $tenant]);

        $this->postJson('/api/v1/roles', ['name' => 'buchhaltung'], ['X-Tenant' => $tenant])
            ->assertStatus(422);

        $adminRole = Role::where('team_id', $tenant)->where('name', 'administrator')->firstOrFail();
        $this->deleteJson("/api/v1/roles/{$adminRole->id}", [], ['X-Tenant' => $tenant])
            ->assertStatus(422);

        $this->deleteJson("/api/v1/roles/{$roleId}", [], ['X-Tenant' => $tenant])
            ->assertNoContent();
        $this->assertDatabaseMissing('roles', ['id' => $roleId]);
    }

    public function test_delete_role_of_foreign_tenant_returns_404(): void
    {
        $tenantA = $this->postJson('/api/v1/tenants', ['name' => 'DelA GmbH'])->json('id');
        $tenantB = $this->postJson('/api/v1/tenants', ['name' => 'DelB GmbH'])->json('id');
        $admin = $this->actingAsUser();

        $roleB = Role::where('team_id', $tenantB)->where('name', 'auditor')->firstOrFail();

        tenancy()->initialize(Tenant::find($tenantA));
        $admin->assignRole('administrator');

        $this->deleteJson("/api/v1/roles/{$roleB->id}", [], ['X-Tenant' => $tenantA])
            ->assertNotFound();
        $this->assertDatabaseHas('roles', ['id' => $roleB->id]);
    }

    public function test_member_actions_emit_user_events(): void
    {
        $tenant = $this->postJson('/api/v1/tenants', ['name' => 'Ev GmbH'])->json('id');
        $admin = $this->actingAsUser();
        tenancy()->initialize(Tenant::find($tenant));
        $admin->assignRole('administrator');

        $this->postJson('/api/v1/users', [
            'name' => 'Evt Mitglied', 'email' => 'evt@example.test', 'roles' => ['mitarbeiter'],
        ], ['X-Tenant' => $tenant])->assertCreated();

        $member = User::where('email', 'evt@example.test')->firstOrFail();

        $events = DB::table('stored_events')
            ->where('meta_data->tenant_id', $tenant)
            ->pluck('event_properties')
            ->map(fn ($p) => json_decode($p, true));
        $types = $events->pluck('type');
        $this->assertContains('user.added', $types);

        $this->putJson("/api/v1/users/{$member->id}/roles", ['roles' => ['auditor']], ['X-Tenant' => $tenant])->assertOk();
        $types = DB::table('stored_events')->where('meta_data->tenant_id', $tenant)
            ->pluck('event_properties')->map(fn ($p) => json_decode($p, true)['type']);
        $this->assertContains('user.roles_updated', $types);

        $this->deleteJson("/api/v1/users/{$member->id}", [], ['X-Tenant' => $tenant])->assertNoContent();
        $types = DB::table('stored_events')->where('meta_data->tenant_id', $tenant)
            ->pluck('event_properties')->map(fn ($p) => json_decode($p, true)['type']);
        $this->assertContains('user.removed', $types);

        $roleRes = $this->postJson('/api/v1/roles', ['name' => 'testrolle'], ['X-Tenant' => $tenant])->assertCreated();
        $roleId = $roleRes->json('id');
        $this->putJson("/api/v1/roles/{$roleId}", ['permissions' => ['tasks.view']], ['X-Tenant' => $tenant])->assertOk();
        $this->deleteJson("/api/v1/roles/{$roleId}", [], ['X-Tenant' => $tenant])->assertNoContent();
        $types = DB::table('stored_events')->where('meta_data->tenant_id', $tenant)
            ->pluck('event_properties')->map(fn ($p) => json_decode($p, true)['type']);
        $this->assertContains('role.created', $types);
        $this->assertContains('role.permissions_updated', $types);
        $this->assertContains('role.deleted', $types);
    }

    public function test_member_actions_notify_user(): void
    {
        $tenant = $this->postJson('/api/v1/tenants', ['name' => 'Notif GmbH'])->json('id');
        $admin = $this->actingAsUser();
        tenancy()->initialize(Tenant::find($tenant));
        $admin->assignRole('administrator');

        $this->postJson('/api/v1/users', [
            'name' => 'N Mitglied', 'email' => 'n@example.test', 'roles' => ['mitarbeiter'],
        ], ['X-Tenant' => $tenant])->assertCreated();

        $member = User::where('email', 'n@example.test')->firstOrFail();

        $this->assertTrue(
            DB::table('notifications')
                ->where('notifiable_id', $member->id)
                ->where('data->kind', 'rollen')
                ->where('data->title', 'like', '%hinzugefügt%')
                ->exists()
        );

        $this->putJson("/api/v1/users/{$member->id}/roles", ['roles' => ['auditor']], ['X-Tenant' => $tenant])->assertOk();
        $this->assertTrue(
            DB::table('notifications')
                ->where('notifiable_id', $member->id)
                ->where('data->kind', 'rollen')
                ->where('data->title', 'like', '%geändert%')
                ->exists()
        );
    }

    public function test_new_member_notifies_roles_manage_users(): void
    {
        $tenant = $this->postJson('/api/v1/tenants', ['name' => 'MJ GmbH'])->json('id');
        $admin = $this->actingAsUser();
        $admin2 = User::factory()->create();
        $worker = User::factory()->create();
        tenancy()->initialize(Tenant::find($tenant));
        $admin->assignRole('administrator');
        $admin2->assignRole('administrator');
        $worker->assignRole('mitarbeiter');

        $this->postJson('/api/v1/users', [
            'name' => 'Neu Mitglied', 'email' => 'neu@example.test', 'roles' => ['kunde'],
        ], ['X-Tenant' => $tenant])->assertCreated();

        $this->assertTrue(
            DB::table('notifications')
                ->where('notifiable_id', $admin2->id)
                ->where('data->kind', 'rollen')
                ->where('data->title', 'like', 'Neues Mitglied:%')
                ->exists()
        );
        $this->assertFalse(
            DB::table('notifications')
                ->where('notifiable_id', $worker->id)
                ->where('data->title', 'like', 'Neues Mitglied:%')
                ->exists()
        );
    }

    public function test_removed_member_notifies_roles_manage_users(): void
    {
        $tenant = $this->postJson('/api/v1/tenants', ['name' => 'MR GmbH'])->json('id');
        $admin = $this->actingAsUser();
        $admin2 = User::factory()->create();
        $worker = User::factory()->create();
        tenancy()->initialize(Tenant::find($tenant));
        $admin->assignRole('administrator');
        $admin2->assignRole('administrator');
        $worker->assignRole('mitarbeiter');

        $this->postJson('/api/v1/users', [
            'name' => 'Weg Mitglied', 'email' => 'weg@example.test', 'roles' => ['kunde'],
        ], ['X-Tenant' => $tenant])->assertCreated();
        $member = User::where('email', 'weg@example.test')->firstOrFail();

        $this->deleteJson("/api/v1/users/{$member->id}", [], ['X-Tenant' => $tenant])->assertNoContent();

        $this->assertTrue(
            DB::table('notifications')
                ->where('notifiable_id', $admin2->id)
                ->where('data->kind', 'rollen')
                ->where('data->title', 'like', 'Mitglied entfernt:%')
                ->exists()
        );
        $this->assertFalse(
            DB::table('notifications')
                ->where('notifiable_id', $worker->id)
                ->where('data->title', 'like', 'Mitglied entfernt:%')
                ->exists()
        );
    }

    public function test_every_permission_domain_in_workspace_map_exists(): void
    {
        $this->postJson('/api/v1/tenants', ['name' => 'Perms GmbH'])->assertCreated();

        $blade = file_get_contents(resource_path('views/workspace.blade.php'));
        preg_match('/permDom\(key\) \{\s*const M = \{([^}]+)\}/', $blade, $m);
        $this->assertNotEmpty($m[1] ?? null, 'permDom map not found in workspace.blade.php');
        preg_match_all("/'([a-z-]+)':'([a-z]+)'/", $m[1], $pairs);
        $domains = array_unique($pairs[2]);
        $this->assertNotEmpty($domains);

        $existing = Permission::pluck('name')->all();
        $expected = ['metrics' => ['metrics.view'], 'roles' => ['roles.manage']];
        foreach ($domains as $d) {
            foreach ($expected[$d] ?? ["{$d}.view", "{$d}.manage"] as $p) {
                $this->assertContains($p, $existing, "{$p} fehlt");
            }
        }
    }

    public function test_tokens_create_list_revoke(): void
    {
        $tenant = Tenant::create(['name' => 'Tok GmbH']);
        $user = $this->actingAsUser($tenant);

        $created = $this->postJson('/api/v1/tokens', ['name' => 'cli'], ['X-Tenant' => $tenant->id])
            ->assertCreated();
        $this->assertNotEmpty($created->json('token'));
        $id = $created->json('id');

        $list = $this->getJson('/api/v1/tokens', ['X-Tenant' => $tenant->id])->assertOk()->json();
        $names = collect($list)->pluck('name');
        $this->assertContains('cli', $names);
        $this->assertArrayNotHasKey('token', $list[0] ?? []);

        // fremder Token eines anderen Users ist nicht löschbar
        $other = User::factory()->create();
        $otherToken = $other->createToken('foreign');
        $this->deleteJson('/api/v1/tokens/'.$otherToken->accessToken->id, [], ['X-Tenant' => $tenant->id])
            ->assertNotFound();

        $this->deleteJson('/api/v1/tokens/'.$id, [], ['X-Tenant' => $tenant->id])->assertOk();
        $this->assertNull($user->tokens()->find($id));
    }

    public function test_tokens_expiry_validation(): void
    {
        $tenant = Tenant::create(['name' => 'Tok2 GmbH']);
        $this->actingAsUser($tenant);

        $this->postJson('/api/v1/tokens', ['name' => 'x', 'expires_in_days' => 0], ['X-Tenant' => $tenant->id])
            ->assertUnprocessable();
    }

    protected function actingAsUser(Tenant|string|null $tenant = null): User
    {
        $user = User::factory()->create();
        if ($tenant) {
            tenancy()->initialize($tenant);
            $user->assignRole('administrator');
        }
        Sanctum::actingAs($user->fresh());

        return $user;
    }
}
