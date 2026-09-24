<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinanceTest extends TestCase
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

    public function test_financial_report_upsert_and_scoping(): void
    {
        $tenantA = Tenant::create(['name' => 'A GmbH']);
        $tenantB = Tenant::create(['name' => 'B GmbH']);
        $user = $this->acting($tenantA);

        $company = $this->postJson('/api/v1/companies', ['name' => 'DentalLab'],
            ['X-Tenant' => $tenantA->id])->assertCreated()->json();

        $r = $this->postJson('/api/v1/financial-reports', [
            'company_id' => $company['id'], 'period' => '2026-08',
            'revenue' => 120000, 'ebitda' => 35000,
        ], ['X-Tenant' => $tenantA->id])->assertCreated()->json();

        // upsert on same company+period does not duplicate
        $this->postJson('/api/v1/financial-reports', [
            'company_id' => $company['id'], 'period' => '2026-08',
            'liquidity' => 80000,
        ], ['X-Tenant' => $tenantA->id])->assertCreated();

        $this->getJson('/api/v1/financial-reports', ['X-Tenant' => $tenantA->id])
            ->assertOk()->assertJsonCount(1, 'data');

        Sanctum::actingAs(User::find($user->id));
        $this->getJson('/api/v1/financial-reports', ['X-Tenant' => $tenantB->id])->assertForbidden();
    }
}
