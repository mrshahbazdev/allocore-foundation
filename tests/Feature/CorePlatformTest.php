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

    protected function actingWithTenant(Tenant $tenant): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_company_crud_is_tenant_scoped(): void
    {
        $tenantA = Tenant::create(['name' => 'A GmbH']);
        $tenantB = Tenant::create(['name' => 'B GmbH']);
        $this->actingWithTenant($tenantA);

        $this->postJson('/api/v1/companies', ['name' => 'DentalTech'], ['X-Tenant' => $tenantA->id])
            ->assertCreated();

        $this->getJson('/api/v1/companies', ['X-Tenant' => $tenantA->id])
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // Same user, other tenant: sees nothing
        $this->getJson('/api/v1/companies', ['X-Tenant' => $tenantB->id])
            ->assertOk()
            ->assertJsonCount(0, 'data');
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

    public function test_tenant_routes_require_auth(): void
    {
        $tenant = Tenant::create(['name' => 'Auth GmbH']);

        $this->getJson('/api/v1/companies', ['X-Tenant' => $tenant->id])
            ->assertUnauthorized();
    }
}
