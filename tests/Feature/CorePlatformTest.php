<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Models\Company;
use Tests\TestCase;

class CorePlatformTest extends TestCase
{
    use RefreshDatabase;

    protected function actingWithTenant(Tenant $tenant, string $role = 'holding'): User
    {
        $user = User::factory()->create();
        tenancy()->initialize($tenant);
        $user->assignRole($role);
        Sanctum::actingAs($user->fresh());

        return $user;
    }

    public function test_company_crud_is_tenant_scoped(): void
    {
        $tenantA = Tenant::create(['name' => 'A GmbH']);
        $tenantB = Tenant::create(['name' => 'B GmbH']);
        $user = $this->actingWithTenant($tenantA);

        $this->postJson('/api/v1/companies', ['name' => 'DentalTech'], ['X-Tenant' => $tenantA->id])
            ->assertCreated();

        $this->getJson('/api/v1/companies', ['X-Tenant' => $tenantA->id])
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // Same user, other tenant: role is per-tenant -> forbidden
        // Fresh user instance: loaded role relations must not leak across tenants.
        Sanctum::actingAs(User::find($user->id));
        $this->getJson('/api/v1/companies', ['X-Tenant' => $tenantB->id])
            ->assertForbidden();
    }

    public function test_company_is_auto_stamped_with_tenant_id(): void
    {
        $tenant = Tenant::create(['name' => 'Stamp GmbH']);
        $this->actingWithTenant($tenant);

        $this->postJson('/api/v1/companies', ['name' => 'Auto'], ['X-Tenant' => $tenant->id])
            ->assertCreated();

        $this->assertSame($tenant->getTenantKey(), Company::withoutGlobalScopes()->first()->tenant_id);
    }

    public function test_task_create_and_list(): void
    {
        $tenant = Tenant::create(['name' => 'Tasks GmbH']);
        $user = $this->actingWithTenant($tenant);

        $this->postJson('/api/v1/tasks', [
            'title' => 'Unterweisung planen',
            'assignee_id' => $user->id,
            'due_at' => now()->addDay()->toISOString(),
        ], ['X-Tenant' => $tenant->id])->assertCreated();

        $this->getJson('/api/v1/tasks', ['X-Tenant' => $tenant->id])
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Unterweisung planen');
    }

    public function test_list_endpoints_honor_per_page_param_and_cap(): void
    {
        $tenant = Tenant::create(['name' => 'Page GmbH']);
        $this->actingWithTenant($tenant);

        foreach (['C1', 'C2', 'C3'] as $name) {
            $this->postJson('/api/v1/companies', ['name' => $name], ['X-Tenant' => $tenant->id])->assertCreated();
        }

        $this->getJson('/api/v1/companies?per_page=2', ['X-Tenant' => $tenant->id])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('total', 3)
            ->assertJsonPath('per_page', 2);

        // per_page is capped at 200
        $this->getJson('/api/v1/companies?per_page=9999', ['X-Tenant' => $tenant->id])
            ->assertOk()
            ->assertJsonPath('per_page', 200);
    }

    public function test_tenant_routes_require_auth(): void
    {
        $tenant = Tenant::create(['name' => 'Auth GmbH']);

        $this->getJson('/api/v1/companies', ['X-Tenant' => $tenant->id])
            ->assertUnauthorized();
    }
}
