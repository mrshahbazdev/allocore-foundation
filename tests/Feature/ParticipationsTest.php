<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ParticipationsTest extends TestCase
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

    public function test_participation_crud_value_change_and_scoping(): void
    {
        $tenantA = Tenant::create(['name' => 'A GmbH']);
        $tenantB = Tenant::create(['name' => 'B GmbH']);
        $user = $this->acting($tenantA);

        $p = $this->postJson('/api/v1/participations', [
            'name' => 'DentalLab Nord GmbH',
            'stake_pct' => 74.5,
            'invested_amount' => 200000,
            'current_valuation' => 260000,
            'capital_need' => 50000,
        ], ['X-Tenant' => $tenantA->id])->assertCreated()->json();

        $this->assertEquals(30.0, $p['value_change_pct']);

        $this->getJson('/api/v1/participations', ['X-Tenant' => $tenantA->id])
            ->assertOk()->assertJsonCount(1, 'data');

        Sanctum::actingAs(User::find($user->id));
        $this->getJson('/api/v1/participations', ['X-Tenant' => $tenantB->id])->assertForbidden();
    }

    public function test_write_requires_manage_permission(): void
    {
        $tenant = Tenant::create(['name' => 'X GmbH']);
        $user = User::factory()->create();
        tenancy()->initialize($tenant);
        $user->assignRole('mitarbeiter');
        Sanctum::actingAs($user->fresh());
        tenancy()->end();

        $this->postJson('/api/v1/participations', ['name' => 'X', 'stake_pct' => 10], ['X-Tenant' => $tenant->id])
            ->assertForbidden();
    }
}
