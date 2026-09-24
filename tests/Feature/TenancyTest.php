<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_tenant_via_the_central_api(): void
    {
        $before = Tenant::count();
        $response = $this->postJson('/api/v1/tenants', ['name' => 'DISAVO Holding GmbH']);

        $response->assertCreated();
        $this->assertSame($before + 1, Tenant::count());
    }

    public function test_lists_tenants_via_the_central_api(): void
    {
        Tenant::create(['name' => 'ALLOCORE GmbH']);

        $this->getJson('/api/v1/tenants')
            ->assertOk()
            ->assertJsonFragment(['name' => 'ALLOCORE GmbH']);
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

    public function test_event_store_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('stored_events'));
        $this->assertTrue(Schema::hasTable('tenants'));
        $this->assertTrue(Schema::hasTable('personal_access_tokens'));
    }
}
