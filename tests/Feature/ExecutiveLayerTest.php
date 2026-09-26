<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExecutiveLayerTest extends TestCase
{
    use RefreshDatabase;

    private function auth(string $role = 'holding'): array
    {
        $tenant = Tenant::create(['name' => 'Exec '.$role]);
        $user = User::factory()->create();
        tenancy()->initialize($tenant);
        $user->assignRole($role);
        Sanctum::actingAs($user->fresh());
        tenancy()->end();

        return [$tenant, $user];
    }

    public function test_overview_rolls_up_all_tenants(): void
    {
        [$tenantA, $user] = $this->auth();
        $tenantB = Tenant::create(['name' => 'Exec B']);
        tenancy()->initialize($tenantB);
        $user->assignRole('holding');
        Sanctum::actingAs($user->fresh());
        tenancy()->end();

        $this->postJson('/api/v1/companies', ['name' => 'ACME', 'domain' => 'acme'], ['X-Tenant' => $tenantA->id]);
        $this->postJson('/api/v1/risk-assessments', [
            'title' => 'GB x', 'hazard' => 'h', 'risk_level' => 'high',
        ], ['X-Tenant' => $tenantB->id]);

        $res = $this->getJson('/api/v1/executive/overview', ['X-Tenant' => $tenantA->id]);
        $res->assertOk()->assertJsonFragment(['name' => 'Exec holding'])->assertJsonFragment(['name' => 'Exec B']);

        $totals = $res->json('totals');
        $this->assertSame(2, $totals['tenants']);
        $this->assertSame(1, $totals['companies']);
        $this->assertSame(1, $totals['high_risks']);
    }

    public function test_exec_report_snapshot(): void
    {
        [$tenant] = $this->auth();

        $res = $this->postJson('/api/v1/exec-reports', ['title' => 'Q3'], ['X-Tenant' => $tenant->id]);
        $res->assertCreated()->assertJsonPath('title', 'Q3');
        $this->assertIsArray($res->json('payload.tenants'));

        $id = $res->json('id');
        $this->getJson("/api/v1/exec-reports/{$id}", ['X-Tenant' => $tenant->id])
            ->assertOk()->assertJsonPath('id', $id);
        $this->getJson('/api/v1/exec-reports', ['X-Tenant' => $tenant->id])
            ->assertOk()->assertJsonFragment(['id' => $id]);
    }

    public function test_rbac_enforcement(): void
    {
        [$tenant] = $this->auth('auditor');

        $this->getJson('/api/v1/executive/overview', ['X-Tenant' => $tenant->id])->assertForbidden();
        $this->postJson('/api/v1/exec-reports', [], ['X-Tenant' => $tenant->id])->assertForbidden();
        $this->getJson('/api/v1/exec-reports', ['X-Tenant' => $tenant->id])->assertForbidden();
    }
}
