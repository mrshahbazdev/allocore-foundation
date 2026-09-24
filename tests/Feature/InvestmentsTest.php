<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvestmentsTest extends TestCase
{
    use RefreshDatabase;

    private function acting(Tenant $tenant): User
    {
        $user = User::factory()->create();
        tenancy()->initialize($tenant);
        $user->assignRole('holding');
        Sanctum::actingAs($user->fresh());
        tenancy()->end();

        return $user;
    }

    public function test_portfolio_investment_crud_and_return_pct(): void
    {
        $tenantA = Tenant::create(['name' => 'A GmbH']);
        $tenantB = Tenant::create(['name' => 'B GmbH']);
        $user = $this->acting($tenantA);

        $portfolio = $this->postJson('/api/v1/portfolios', [
            'name' => 'Depot Holding',
            'type' => 'securities',
        ], ['X-Tenant' => $tenantA->id])->assertCreated()->json();

        $inv = $this->postJson('/api/v1/investments', [
            'portfolio_id' => $portfolio['id'],
            'name' => 'ACME Aktie',
            'asset_class' => 'equity',
            'quantity' => 10,
            'cost_basis' => 1000,
            'current_value' => 1250,
        ], ['X-Tenant' => $tenantA->id])->assertCreated()->json();

        $this->assertEquals(25.0, $inv['return_pct']);

        $this->getJson("/api/v1/portfolios/{$portfolio['id']}", ['X-Tenant' => $tenantA->id])
            ->assertOk()
            ->assertJsonPath('summary.cost_basis', 10000)
            ->assertJsonPath('summary.current_value', 12500);

        Sanctum::actingAs(User::find($user->id));
        $this->getJson('/api/v1/portfolios', ['X-Tenant' => $tenantB->id])->assertForbidden();
    }

    public function test_view_only_role_cannot_write(): void
    {
        $tenant = Tenant::create(['name' => 'X GmbH']);
        $user = User::factory()->create();
        tenancy()->initialize($tenant);
        $user->assignRole('auditor');
        Sanctum::actingAs($user->fresh());
        tenancy()->end();

        $this->getJson('/api/v1/portfolios', ['X-Tenant' => $tenant->id])->assertForbidden(); // auditor has no investments.view
        $this->postJson('/api/v1/portfolios', ['name' => 'X'], ['X-Tenant' => $tenant->id])->assertForbidden();
    }
}
