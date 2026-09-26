<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Notifications\FailedLoginAlert;
use Modules\Core\Notifications\NewLoginAlert;
use Modules\Core\Notifications\PasswordChangedAlert;
use Modules\DataPlatform\Events\DomainEvent;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_creation_seeds_rollenmodell(): void
    {
        $tenant = $this->createTenantApi(['name' => 'Rollen GmbH'])
            ->assertCreated()->json('id');

        $this->actingAsUser(Tenant::find($tenant));

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
        $tenant = $this->createTenantApi(['name' => 'Assign GmbH'])->json('id');
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
        $tenant = $this->createTenantApi(['name' => 'Members GmbH'])->json('id');
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
        $tenant = $this->createTenantApi(['name' => 'UserCreate GmbH'])->json('id');
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
        $tenantA = $this->createTenantApi(['name' => 'ScopeA GmbH'])->json('id');
        $tenantB = $this->createTenantApi(['name' => 'ScopeB GmbH'])->json('id');
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
        $tenant = $this->createTenantApi(['name' => 'NoPerm GmbH'])->json('id');
        $this->actingAsUser();
        $target = User::factory()->create();

        $this->putJson("/api/v1/users/{$target->id}/roles", ['roles' => ['auditor']], ['X-Tenant' => $tenant])
            ->assertForbidden();
    }

    public function test_me_returns_current_user_roles_and_permissions(): void
    {
        $tenant = $this->createTenantApi(['name' => 'Me GmbH'])->json('id');
        $user = $this->actingAsUser();

        tenancy()->initialize(Tenant::find($tenant));
        $user->assignRole('auditor');

        $res = $this->getJson('/api/v1/me', ['X-Tenant' => $tenant])->assertOk()->assertJsonStructure(['last_login_at']);
        $res->assertJsonPath('id', $user->id)
            ->assertJsonPath('email', $user->email)
            ->assertJsonPath('roles.0', 'auditor');
        $this->assertContains('compliance.view', $res->json('permissions'));
        $res->assertJsonPath('tenants.0.id', $tenant)
            ->assertJsonPath('tenants.0.name', 'Me GmbH')
            ->assertJsonPath('tenants.0.roles.0', 'auditor');
    }

    public function test_admin_can_remove_member_but_not_self(): void
    {
        $tenant = $this->createTenantApi(['name' => 'Remove GmbH'])->json('id');
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

    public function test_profile_update_records_user_updated_event(): void
    {
        $tenant = $this->createTenantApi(['name' => 'Profil GmbH'])->json('id');
        $user = $this->actingAsUser();

        tenancy()->initialize(Tenant::find($tenant));
        $user->assignRole('auditor');
        tenancy()->end();

        $this->putJson('/api/v1/me', ['name' => 'Neuer Name'], ['X-Tenant' => $tenant])
            ->assertOk();

        $this->assertDatabaseHas('stored_events', [
            'event_class' => DomainEvent::class,
            'meta_data->tenant_id' => $tenant,
        ]);
        $this->assertTrue(
            \DB::table('stored_events')
                ->where('event_properties->type', 'user.updated')
                ->where('event_properties->subject->id', $user->id)
                ->exists()
        );
    }

    public function test_password_change_notifies_user(): void
    {
        $tenant = $this->createTenantApi(['name' => 'PW GmbH'])->json('id');
        $user = $this->actingAsUser($tenant);

        $this->putJson('/api/v1/me/password', [
            'current_password' => 'password',
            'password' => 'NeuPasswort123',
            'password_confirmation' => 'NeuPasswort123',
        ], ['X-Tenant' => $tenant])->assertOk();

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user->id,
            'notifiable_type' => User::class,
        ]);
    }

    public function test_notification_prefs_mute_kinds(): void
    {
        $tenant = Tenant::create(['name' => 'NPM GmbH']);
        $user = $this->actingAsUser($tenant);

        $this->putJson('/api/v1/me/notification-prefs', ['muted_kinds' => ['passwort_geaendert']], ['X-Tenant' => $tenant->id])
            ->assertOk()->assertJsonPath('muted_kinds.0', 'passwort_geaendert');

        $this->assertSame(['passwort_geaendert'], $user->fresh()->notification_muted);

        $this->getJson('/api/v1/me', ['X-Tenant' => $tenant->id])
            ->assertJsonPath('muted_kinds.0', 'passwort_geaendert');

        $user->notify(new PasswordChangedAlert('T'));
        $rows = $this->getJson('/api/v1/notifications', ['X-Tenant' => $tenant->id])->json();
        $this->assertTrue($rows[0]['muted']);
    }

    public function test_delete_all_tokens_endpoint(): void
    {
        $tenant = Tenant::create(['name' => 'TokAll GmbH']);
        $user = $this->actingAsUser($tenant);
        $plain = $user->createToken('keep')->plainTextToken;
        $user->createToken('x');
        $user->createToken('y');
        $this->deleteJson('/api/v1/tokens', [], ['X-Tenant' => $tenant->id])
            ->assertOk()
            ->assertJson(['status' => 'ok', 'deleted' => 3]);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_login_records_event_in_tenant_feed(): void
    {
        $tenant = Tenant::create(['name' => 'EVT']);
        $user = User::factory()->create(['password' => 'NeuPasswort123']);
        PermissionRegistrar::class;
        Role::firstOrCreate(['name' => 'administrator', 'guard_name' => 'web']);
        $user->assignRole(Role::where('name', 'administrator')->first());
        \DB::table('model_has_roles')->where('model_id', $user->id)->update(['team_id' => $tenant->id]);

        $this->post('/login', ['email' => $user->email, 'password' => 'NeuPasswort123']);

        $this->assertTrue(
            \DB::table('stored_events')
                ->where('event_properties->type', 'user.logged_in')
                ->where('meta_data->tenant_id', (string) $tenant->id)
                ->exists()
        );
    }

    public function test_logout_records_event_in_tenant_feed(): void
    {
        $tenant = Tenant::create(['name' => 'EVT']);
        $user = User::factory()->create(['password' => 'NeuPasswort123']);
        Role::firstOrCreate(['name' => 'administrator', 'guard_name' => 'web']);
        $user->assignRole(Role::where('name', 'administrator')->first());
        \DB::table('model_has_roles')->where('model_id', $user->id)->update(['team_id' => $tenant->id]);

        $this->post('/login', ['email' => $user->email, 'password' => 'NeuPasswort123']);
        $this->post('/logout');

        $this->assertTrue(
            \DB::table('stored_events')
                ->where('event_properties->type', 'user.logged_out')
                ->where('meta_data->tenant_id', (string) $tenant->id)
                ->exists()
        );
    }

    public function test_login_lockout_notifies_user_once(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => 'NeuPasswort123']);

        for ($i = 0; $i < 7; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'falsches-Passwort']);
        }

        Notification::assertSentToTimes($user, FailedLoginAlert::class, 1);
    }

    public function test_login_from_new_ip_notifies_user(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'password' => 'NeuPasswort123',
            'last_login_ip' => '10.0.0.1',
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => 'NeuPasswort123'],
            ['REMOTE_ADDR' => '10.0.0.2']);

        Notification::assertSentTo($user, NewLoginAlert::class);
    }

    public function test_email_change_resets_email_verified_at(): void
    {
        $tenant = $this->createTenantApi(['name' => 'Mail GmbH'])->json('id');
        $user = $this->actingAsUser();
        $user->forceFill(['email_verified_at' => now()])->save();

        tenancy()->initialize(Tenant::find($tenant));
        $user->assignRole('auditor');
        tenancy()->end();

        $this->putJson('/api/v1/me', ['email' => 'neu@example.test'], ['X-Tenant' => $tenant])
            ->assertOk();

        $this->assertNull($user->fresh()->email_verified_at);

        Sanctum::actingAs($user->fresh());
        $this->getJson('/api/v1/me', ['X-Tenant' => $tenant])
            ->assertOk()->assertJson(['email_verified' => false]);
    }

    public function test_password_update_stamps_password_changed_at_and_me_returns_it(): void
    {
        $tenant = $this->createTenantApi(['name' => 'Pw GmbH'])->json('id');
        $user = $this->actingAsUser();

        tenancy()->initialize(Tenant::find($tenant));
        $user->assignRole('auditor');
        tenancy()->end();

        $this->putJson('/api/v1/me/password', [
            'current_password' => 'password',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ], ['X-Tenant' => $tenant])->assertOk();

        $this->assertNotNull($user->fresh()->password_changed_at);

        Sanctum::actingAs($user->fresh());
        $this->getJson('/api/v1/me', ['X-Tenant' => $tenant])
            ->assertOk()
            ->assertJsonStructure(['password_changed_at']);
    }

    public function test_member_can_leave_tenant_but_last_manager_cannot(): void
    {
        $tenant = $this->createTenantApi(['name' => 'Leave GmbH'])->json('id');
        $admin = $this->actingAsUser();
        $member = User::factory()->create();

        tenancy()->initialize(Tenant::find($tenant));
        $admin->assignRole('administrator');
        $member->assignRole('mitarbeiter');
        tenancy()->end();

        Sanctum::actingAs($admin);

        // Mandant-Ersteller (auto-'administrator') entfernen → $admin ist letzter Manager
        DB::table('model_has_roles')
            ->where('team_id', $tenant)
            ->where('model_id', '!=', $admin->id)
            ->whereIn('role_id', Role::where('team_id', $tenant)->where('name', 'administrator')->pluck('id'))
            ->delete();

        $this->deleteJson('/api/v1/me/membership', [], ['X-Tenant' => $tenant])
            ->assertStatus(422);

        Sanctum::actingAs($member);
        $this->deleteJson('/api/v1/me/membership', [], ['X-Tenant' => $tenant])
            ->assertNoContent();

        $this->getJson('/api/v1/users', ['X-Tenant' => $tenant])->assertForbidden();
    }

    public function test_delete_me_requires_no_memberships_and_revokes_tokens(): void
    {
        $tenant = $this->createTenantApi(['name' => 'Delete GmbH'])->json('id');
        $user = $this->actingAsUser();

        tenancy()->initialize(Tenant::find($tenant));
        $user->assignRole('mitarbeiter');
        tenancy()->end();

        $token = $user->createToken('api')->plainTextToken;

        $this->deleteJson('/api/v1/me', [], ['X-Tenant' => $tenant])
            ->assertStatus(422);

        DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->where('model_id', $user->id)
            ->delete();

        Sanctum::actingAs($user->fresh());
        $this->deleteJson('/api/v1/me', [], ['X-Tenant' => $tenant])
            ->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id, 'tokenable_type' => User::class]);
    }

    public function test_role_update_requires_roles_manage_and_same_tenant(): void
    {
        $tenant = $this->createTenantApi(['name' => 'Guard GmbH'])->json('id');
        $other = $this->createTenantApi(['name' => 'Fremd GmbH'])->json('id');
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
        $tenant = $this->createTenantApi(['name' => 'PermsEdit GmbH'])->json('id');
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
        $tenant = $this->createTenantApi(['name' => 'RoleCrud GmbH'])->json('id');
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
        $tenantA = $this->createTenantApi(['name' => 'DelA GmbH'])->json('id');
        $tenantB = $this->createTenantApi(['name' => 'DelB GmbH'])->json('id');
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
        $tenant = $this->createTenantApi(['name' => 'Ev GmbH'])->json('id');
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
        $tenant = $this->createTenantApi(['name' => 'Notif GmbH'])->json('id');
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
        $tenant = $this->createTenantApi(['name' => 'MJ GmbH'])->json('id');
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
        $tenant = $this->createTenantApi(['name' => 'MR GmbH'])->json('id');
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
        $this->createTenantApi(['name' => 'Perms GmbH'])->assertCreated();

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

    public function test_scoped_token_enforces_abilities(): void
    {
        $tenant = Tenant::create(['name' => 'Scope GmbH']);
        $user = User::factory()->create();
        tenancy()->initialize($tenant);
        $user->assignRole('administrator');

        $plain = $user->fresh()->createToken('ro', ['tasks.view'])->plainTextToken;
        $h = ['Authorization' => 'Bearer '.$plain, 'X-Tenant' => $tenant->id];

        $this->getJson('/api/v1/tasks', $h)->assertOk();
        $this->postJson('/api/v1/tasks', ['title' => 'x'], $h)->assertForbidden();
    }

    public function test_token_abilities_validation(): void
    {
        $tenant = Tenant::create(['name' => 'Scope2 GmbH']);
        $this->actingAsUser($tenant);

        $this->postJson('/api/v1/tokens', ['name' => 'x', 'abilities' => ['nope.wrong']], ['X-Tenant' => $tenant->id])
            ->assertUnprocessable();
        $this->postJson('/api/v1/tokens', ['name' => 'x', 'abilities' => ['tasks.view']], ['X-Tenant' => $tenant->id])
            ->assertCreated()
            ->assertJsonPath('abilities.0', 'tasks.view');
    }

    public function test_tokens_prune_removes_expired_and_stale_workspace_tokens(): void
    {
        $tenant = Tenant::create(['name' => 'Prune GmbH']);
        $user = $this->actingAsUser($tenant);

        $exp = $user->fresh()->createToken('exp', ['*'], now()->subDay());
        $ws = $user->fresh()->createToken('workspace');
        $ws->accessToken->forceFill(['created_at' => now()->subDays(3)])->save();
        $keep = $user->fresh()->createToken('keep', ['*'], now()->addDays(5));

        $this->artisan('tokens:prune')->assertSuccessful();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $exp->accessToken->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $ws->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $keep->accessToken->id]);
    }

    public function test_missing_tenant_header_returns_400(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/tasks')
            ->assertStatus(400)
            ->assertJsonPath('message', 'X-Tenant Header fehlt oder Mandant unbekannt.');
    }

    public function test_api_rate_limit(): void
    {
        $tenant = Tenant::create(['name' => 'RL GmbH']);
        $this->actingAsUser($tenant);
        $response = null;
        foreach (range(1, 121) as $i) {
            $response = $this->getJson('/api/v1/me', ['X-Tenant' => $tenant->id]);
        }
        $response->assertStatus(429);
    }

    public function test_eingeschraenkter_roles_manager_kann_sich_nicht_eskalieren(): void
    {
        $tenant = Tenant::find($this->createTenantApi(['name' => 'Guard GmbH'])->json('id'));
        $target = User::factory()->create();

        // Rolle mit roles.manage aber ohne 'ai.manage' anlegen (als Admin).
        $admin = $this->actingAsUser($tenant);
        $perms = Permission::pluck('name')->reject(fn ($p) => $p === 'ai.manage')->values();
        $teamleiter = new Role;
        $teamleiter->name = 'teamleiter';
        $teamleiter->guard_name = 'web';
        $teamleiter->team_id = $tenant->getTenantKey();
        $teamleiter->save();
        $teamleiter->syncPermissions($perms);

        // Mitglied mit dieser Rolle einloggen.
        $manager = User::factory()->create();
        tenancy()->initialize($tenant);
        $manager->assignRole('teamleiter');
        Sanctum::actingAs($manager->fresh());

        // 'administrator' hat 'ai.manage' -> 403.
        $this->putJson("/api/v1/users/{$target->id}/roles", ['roles' => ['administrator']], ['X-Tenant' => $tenant->id])
            ->assertForbidden();

        // 'mitarbeiter' ist Teilmenge -> ok.
        $this->putJson("/api/v1/users/{$target->id}/roles", ['roles' => ['mitarbeiter']], ['X-Tenant' => $tenant->id])
            ->assertOk();

        // Eigene Rechte uebersteigende Rolle anlegen -> 403.
        $this->postJson('/api/v1/roles', ['name' => 'superrolle', 'permissions' => ['ai.manage']], ['X-Tenant' => $tenant->id])
            ->assertForbidden();

        // Staerkere Rolle editieren/loeschen -> 403.
        $adminRole = Role::where('team_id', $tenant->getTenantKey())->where('name', 'geschaeftsfuehrer')->first();
        $this->putJson("/api/v1/roles/{$adminRole->id}", ['permissions' => ['tasks.view']], ['X-Tenant' => $tenant->id])
            ->assertForbidden();
        $this->deleteJson("/api/v1/roles/{$adminRole->id}", [], ['X-Tenant' => $tenant->id])
            ->assertForbidden();
    }

    public function test_letzter_roles_manager_kann_nicht_abgestuft_oder_entfernt_werden(): void
    {
        $tenant = Tenant::find($this->createTenantApi(['name' => 'Lockout GmbH'])->json('id'));
        $admin = $this->actingAsUser($tenant);

        // Andere roles.manager (z. B. den Tenant-Ersteller) zuerst abstufen.
        $otherManagerIds = DB::table('model_has_roles')
            ->where('team_id', $tenant->getTenantKey())
            ->where('model_type', User::class)
            ->where('model_id', '!=', $admin->id)
            ->pluck('model_id');
        foreach ($otherManagerIds as $id) {
            $this->putJson("/api/v1/users/{$id}/roles", ['roles' => ['mitarbeiter']], ['X-Tenant' => $tenant->id])
                ->assertOk();
        }

        // Jetzt ist $admin der letzte roles.manager — Abstufung muss 422 liefern.
        $this->putJson("/api/v1/users/{$admin->id}/roles", ['roles' => ['mitarbeiter']], ['X-Tenant' => $tenant->id])
            ->assertStatus(422);

        // Zweiten Admin hinzufügen -> Abstufung jetzt erlaubt.
        $second = User::factory()->create();
        tenancy()->initialize($tenant);
        $second->assignRole('administrator');
        $this->putJson("/api/v1/users/{$admin->id}/roles", ['roles' => ['mitarbeiter']], ['X-Tenant' => $tenant->id])
            ->assertOk();

        // Ab hier als zweiter Admin agieren ($admin ist jetzt mitarbeiter).
        Sanctum::actingAs($second->fresh());

        // Letzte roles.manage-Rolle löschen -> 422. Erst 'holding' entfernen, dann 'administrator'.
        $holding = Role::where('team_id', $tenant->getTenantKey())->where('name', 'holding')->first();
        $this->deleteJson("/api/v1/roles/{$holding->id}", [], ['X-Tenant' => $tenant->id])
            ->assertStatus(422); // System-Rolle

        $admRole = Role::where('team_id', $tenant->getTenantKey())->where('name', 'administrator')->first();
        $this->deleteJson("/api/v1/roles/{$admRole->id}", [], ['X-Tenant' => $tenant->id])
            ->assertStatus(422); // System-Rolle schützt ohnehin

        // Update der letzten roles.manage-Rolle ohne roles.manage -> 422.
        $this->putJson("/api/v1/roles/{$admRole->id}", ['permissions' => ['tasks.view']], ['X-Tenant' => $tenant->id])
            ->assertStatus(422);
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

    protected function createTenantApi(array $data)
    {
        Sanctum::actingAs(User::factory()->create());

        return $this->postJson('/api/v1/tenants', $data);
    }
}
