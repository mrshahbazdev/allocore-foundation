<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiLayerTest extends TestCase
{
    use RefreshDatabase;

    private function auth(string $role = 'holding'): array
    {
        $tenant = Tenant::create(['name' => 'Ai '.$role]);
        $user = User::factory()->create();
        tenancy()->initialize($tenant);
        $user->assignRole($role);
        Sanctum::actingAs($user->fresh());
        tenancy()->end();

        return [$tenant, $user];
    }

    public function test_create_and_read_analysis(): void
    {
        [$tenant] = $this->auth();

        $res = $this->postJson('/api/v1/ai-analyses', [], ['X-Tenant' => $tenant->id]);
        $res->assertCreated()
            ->assertJsonPath('provider', 'heuristic')
            ->assertJsonPath('status', 'completed');
        $this->assertNotEmpty($res->json('summary'));

        $id = $res->json('id');
        $this->getJson('/api/v1/ai-analyses', ['X-Tenant' => $tenant->id])
            ->assertOk()->assertJsonFragment(['id' => $id]);
        $this->getJson('/api/v1/ai-analyses?status=completed', ['X-Tenant' => $tenant->id])
            ->assertOk()->assertJsonFragment(['id' => $id]);
        $this->getJson('/api/v1/ai-analyses?status=failed', ['X-Tenant' => $tenant->id])
            ->assertOk()->assertJsonMissing(['id' => $id]);
        $this->getJson('/api/v1/ai-analyses?kind=analysis', ['X-Tenant' => $tenant->id])
            ->assertOk()->assertJsonFragment(['id' => $id]);
        $this->getJson('/api/v1/ai-analyses?kind=other', ['X-Tenant' => $tenant->id])
            ->assertOk()->assertJsonMissing(['id' => $id]);
        $this->getJson("/api/v1/ai-analyses/{$id}", ['X-Tenant' => $tenant->id])
            ->assertOk()->assertJsonPath('id', $id);
    }

    public function test_analysis_reflects_tenant_data(): void
    {
        [$tenant] = $this->auth();

        $this->postJson('/api/v1/risk-assessments', [
            'title' => 'GB roof', 'hazard' => 'falls', 'risk_level' => 'high',
        ], ['X-Tenant' => $tenant->id]);

        $res = $this->postJson('/api/v1/ai-analyses', [], ['X-Tenant' => $tenant->id]);
        $res->assertCreated();
        $this->assertStringContainsString('high', $res->json('findings.0.code') ?? '');
    }

    public function test_cross_tenant_isolation(): void
    {
        [$tenantA, $user] = $this->auth();
        $tenantB = Tenant::create(['name' => 'Ai B']);
        tenancy()->initialize($tenantB);
        $user->assignRole('mitarbeiter');
        Sanctum::actingAs($user->fresh());
        tenancy()->end();

        $res = $this->postJson('/api/v1/ai-analyses', [], ['X-Tenant' => $tenantA->id]);
        $id = $res->json('id');

        $this->getJson("/api/v1/ai-analyses/{$id}", ['X-Tenant' => $tenantB->id])->assertNotFound();
        $this->getJson('/api/v1/ai-analyses', ['X-Tenant' => $tenantB->id])
            ->assertOk()->assertJsonMissing(['id' => $id]);
    }

    public function test_rbac_enforcement(): void
    {
        [$tenant] = $this->auth('auditor');

        $this->postJson('/api/v1/ai-analyses', [], ['X-Tenant' => $tenant->id])->assertForbidden();
        $this->getJson('/api/v1/ai-analyses', ['X-Tenant' => $tenant->id])->assertForbidden();
    }
}
