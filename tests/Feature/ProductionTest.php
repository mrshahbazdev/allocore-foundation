<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductionTest extends TestCase
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

    public function test_machine_and_order_flow(): void
    {
        $tenantA = Tenant::create(['name' => 'A GmbH']);
        $tenantB = Tenant::create(['name' => 'B GmbH']);
        $user = $this->acting($tenantA);

        $machine = $this->postJson('/api/v1/machines', [
            'name' => 'Fraesmaschine 1',
            'type' => 'milling',
            'capacity_units_per_day' => 12,
        ], ['X-Tenant' => $tenantA->id])->assertCreated()->json();

        $order = $this->postJson('/api/v1/production-orders', [
            'order_no' => 'AUF-1001',
            'product' => 'Krone Zirkon',
            'quantity' => 20,
            'machine_id' => $machine['id'],
            'due_at' => today()->addDays(3)->toDateString(),
        ], ['X-Tenant' => $tenantA->id])->assertCreated()->json();

        $updated = $this->putJson("/api/v1/production-orders/{$order['id']}", [
            'status' => 'running',
        ], ['X-Tenant' => $tenantA->id])->assertOk()->json();
        $this->assertNotNull($updated['started_at']);

        $updated = $this->putJson("/api/v1/production-orders/{$order['id']}", [
            'status' => 'done',
            'scrap_qty' => 2,
        ], ['X-Tenant' => $tenantA->id])->assertOk()->json();
        $this->assertNotNull($updated['finished_at']);
        $this->assertEquals(10.0, $updated['scrap_pct']);

        $this->getJson('/api/v1/machines', ['X-Tenant' => $tenantA->id])
            ->assertOk()->assertJsonPath('data.0.open_orders_count', 0);

        Sanctum::actingAs(User::find($user->id));
        $this->getJson('/api/v1/production-orders', ['X-Tenant' => $tenantB->id])->assertForbidden();
    }

    public function test_write_requires_manage_permission(): void
    {
        $tenant = Tenant::create(['name' => 'X GmbH']);
        $user = User::factory()->create();
        tenancy()->initialize($tenant);
        $user->assignRole('mitarbeiter');
        Sanctum::actingAs($user->fresh());
        tenancy()->end();

        $this->postJson('/api/v1/machines', ['name' => 'M1'], ['X-Tenant' => $tenant->id])->assertForbidden();
    }
}
