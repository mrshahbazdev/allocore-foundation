<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class KnowledgeGraphTest extends TestCase
{
    use RefreshDatabase;

    private function auth(string $role = 'holding'): array
    {
        $tenant = Tenant::create(['name' => 'Graph '.$role]);
        $user = User::factory()->create();
        tenancy()->initialize($tenant);
        $user->assignRole($role);
        Sanctum::actingAs($user->fresh());
        tenancy()->end();

        return [$tenant, $user];
    }

    public function test_entity_edge_crud_and_traversal(): void
    {
        [$tenant] = $this->auth();
        $h = ['X-Tenant' => $tenant->id];

        $a = $this->postJson('/api/v1/graph-entities', [
            'type' => 'company', 'name' => 'DISAVO Holding',
        ], $h)->assertCreated()->json('id');
        $b = $this->postJson('/api/v1/graph-entities', [
            'type' => 'person', 'name' => 'Max Mustermann',
        ], $h)->assertCreated()->json('id');
        $c = $this->postJson('/api/v1/graph-entities', [
            'type' => 'company', 'name' => 'DentalTech GmbH',
        ], $h)->assertCreated()->json('id');

        $this->postJson('/api/v1/graph-edges', [
            'from_entity_id' => $b, 'to_entity_id' => $a, 'relation' => 'works_at',
        ], $h)->assertCreated();
        $this->postJson('/api/v1/graph-edges', [
            'from_entity_id' => $a, 'to_entity_id' => $c, 'relation' => 'owns',
        ], $h)->assertCreated();

        // dedupe: same edge again is idempotent
        $this->postJson('/api/v1/graph-edges', [
            'from_entity_id' => $b, 'to_entity_id' => $a, 'relation' => 'works_at',
        ], $h)->assertCreated();
        $this->assertEquals(2, $this->getJson('/api/v1/graph-edges', $h)->json('total'));

        $res = $this->getJson("/api/v1/graph-entities/{$b}/neighbors?depth=2", $h)->assertOk();
        $names = collect($res->json('nodes'))->pluck('name');
        $this->assertContains('DISAVO Holding', $names);
        $this->assertContains('DentalTech GmbH', $names);
        $this->assertCount(2, $res->json('edges'));

        $this->deleteJson("/api/v1/graph-entities/{$a}", [], $h)->assertNoContent();
        $this->getJson("/api/v1/graph-entities/{$a}", $h)->assertNotFound();
    }

    public function test_cross_tenant_isolation(): void
    {
        [$tenantA, $user] = $this->auth();
        $tenantB = Tenant::create(['name' => 'Graph B']);
        tenancy()->initialize($tenantB);
        $user->assignRole('mitarbeiter');
        Sanctum::actingAs($user->fresh());
        tenancy()->end();

        $id = $this->postJson('/api/v1/graph-entities', [
            'type' => 'x', 'name' => 'A-entity',
        ], ['X-Tenant' => $tenantA->id])->json('id');

        $this->getJson("/api/v1/graph-entities/{$id}", ['X-Tenant' => $tenantB->id])
            ->assertNotFound();
        $this->getJson('/api/v1/graph-entities', ['X-Tenant' => $tenantB->id])
            ->assertJsonCount(0, 'data');
    }
}
