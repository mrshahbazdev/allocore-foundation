<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_tenant_via_the_central_api(): void
    {
        $before = Tenant::count();
        $response = $this->createTenantApi(['name' => 'DISAVO Holding GmbH']);

        $response->assertCreated();
        $this->assertSame($before + 1, Tenant::count());
    }

    public function test_tenant_creation_emits_event(): void
    {
        $response = $this->createTenantApi(['name' => 'Event GmbH']);

        $id = $response->json('id');
        $types = DB::table('stored_events')
            ->where('meta_data->tenant_id', $id)
            ->pluck('event_properties')
            ->map(fn ($p) => json_decode($p, true)['type'] ?? null)
            ->all();

        $this->assertContains('tenant.created', $types);
    }

    public function test_tenant_creator_wird_administrator(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $tenantId = $this->postJson('/api/v1/tenants', ['name' => 'Neue GmbH'])
            ->assertCreated()->json('id');

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
        $this->assertTrue($user->fresh()->hasRole('administrator'));
    }

    public function test_non_member_cannot_enter_tenant(): void
    {
        $tenantA = Tenant::create(['name' => 'Mitglied GmbH']);
        $tenantB = Tenant::create(['name' => 'Fremd GmbH']);

        RoleSeeder::forTenant($tenantA);

        $user = User::factory()->create();
        $user->assignRole('mitarbeiter');

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/context', ['X-Tenant' => $tenantA->id])->assertOk();
        $this->getJson('/api/v1/context', ['X-Tenant' => $tenantB->id])->assertForbidden();
    }

    public function test_user_without_memberships_cannot_enter_tenant(): void
    {
        $tenant = Tenant::create(['name' => 'Offen GmbH']);

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/context', ['X-Tenant' => $tenant->id])->assertForbidden();
    }

    public function test_tenants_endpoints_require_auth(): void
    {
        $this->getJson('/api/v1/tenants')->assertUnauthorized();
        $this->postJson('/api/v1/tenants', ['name' => 'X GmbH'])->assertUnauthorized();
    }

    public function test_lists_tenants_via_the_central_api(): void
    {
        Tenant::create(['name' => 'Fremde GmbH']);

        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/tenants', ['name' => 'ALLOCORE GmbH'])->assertCreated();

        $this->getJson('/api/v1/tenants')
            ->assertOk()
            ->assertJsonFragment(['name' => 'ALLOCORE GmbH'])
            ->assertJsonMissing(['name' => 'Fremde GmbH']);
    }

    public function test_initializes_tenancy_context_for_a_tenant(): void
    {
        $tenant = Tenant::create(['name' => 'DentalTech GmbH']);

        tenancy()->initialize($tenant);

        $this->assertTrue(tenancy()->initialized);
        $this->assertSame($tenant->getTenantKey(), tenant()->getTenantKey());

        tenancy()->end();

        $this->assertFalse(tenancy()->initialized);
    }

    public function test_scopes_permission_teams_to_the_tenant_id(): void
    {
        $tenant = Tenant::create(['name' => 'Scoped GmbH']);
        tenancy()->initialize($tenant);

        $resolver = app(config('permission.team_resolver'));

        $this->assertSame($tenant->getTenantKey(), $resolver->getPermissionsTeamId());

        tenancy()->end();
    }

    public function test_tenant_rename_emits_event_and_requires_permission(): void
    {
        $tenant = Tenant::create(['name' => 'Alt GmbH']);

        Sanctum::actingAs(User::factory()->create());
        $this->putJson('/api/v1/tenant', ['name' => 'Neu GmbH'], ['X-Tenant' => $tenant->id])
            ->assertForbidden();

        tenancy()->initialize($tenant);
        $admin = User::factory()->create();
        $admin->assignRole('administrator');
        Sanctum::actingAs($admin->fresh());
        tenancy()->end();

        $this->putJson('/api/v1/tenant', ['name' => 'Neu GmbH'], ['X-Tenant' => $tenant->id])
            ->assertOk();
        $this->assertSame('Neu GmbH', $tenant->fresh()->name);

        $types = DB::table('stored_events')
            ->where('meta_data->tenant_id', $tenant->id)
            ->pluck('event_properties')
            ->map(fn ($p) => json_decode($p, true)['type'] ?? null)
            ->all();
        $this->assertContains('tenant.updated', $types);
    }

    public function test_event_store_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('stored_events'));
        $this->assertTrue(Schema::hasTable('tenants'));
        $this->assertTrue(Schema::hasTable('personal_access_tokens'));
    }

    protected function createTenantApi(array $data)
    {
        Sanctum::actingAs(User::factory()->create());

        return $this->postJson('/api/v1/tenants', $data);
    }
}
