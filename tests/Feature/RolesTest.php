<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_creation_seeds_rollenmodell(): void
    {
        $tenant = $this->postJson('/api/v1/tenants', ['name' => 'Rollen GmbH'])
            ->assertCreated()->json('id');

        $this->actingAsUser();

        $res = $this->getJson('/api/v1/roles', ['X-Tenant' => $tenant])->assertOk();

        $names = collect($res->json())->pluck('name')->sort()->values();
        $this->assertEquals(
            ['administrator', 'auditor', 'berater', 'geschaeftsfuehrer', 'holding', 'kunde', 'mitarbeiter'],
            $names->all(),
        );

        $holding = collect($res->json())->firstWhere('name', 'holding');
        $this->assertContains('companies.manage', collect($holding['permissions'])->pluck('name'));
    }

    public function test_admin_can_assign_roles_to_user(): void
    {
        $tenant = $this->postJson('/api/v1/tenants', ['name' => 'Assign GmbH'])->json('id');
        $admin = $this->actingAsUser();
        $target = User::factory()->create();

        tenancy()->initialize(Tenant::find($tenant));
        $admin->assignRole('administrator');

        $this->putJson("/api/v1/users/{$target->id}/roles", ['roles' => ['auditor']], ['X-Tenant' => $tenant])
            ->assertOk()->assertJsonPath('roles.0', 'auditor');

        $this->getJson("/api/v1/users/{$target->id}/roles", ['X-Tenant' => $tenant])
            ->assertOk()->assertJsonPath('roles.0', 'auditor')
            ->assertJson(fn ($json) => $json->where('roles.0', 'auditor')
                ->whereType('permissions', 'array')
                ->etc());

        $this->assertContains('compliance.view', $this->getJson("/api/v1/users/{$target->id}/roles", ['X-Tenant' => $tenant])->json('permissions'));
    }

    public function test_users_endpoint_returns_only_tenant_members(): void
    {
        $tenant = $this->postJson('/api/v1/tenants', ['name' => 'Members GmbH'])->json('id');
        $admin = $this->actingAsUser();
        $member = User::factory()->create();
        $outsider = User::factory()->create();

        tenancy()->initialize(Tenant::find($tenant));
        $admin->assignRole('administrator');
        $member->assignRole('mitarbeiter');

        $ids = collect($this->getJson('/api/v1/users', ['X-Tenant' => $tenant])->assertOk()->json())->pluck('id');

        $this->assertContains($admin->id, $ids);
        $this->assertContains($member->id, $ids);
        $this->assertNotContains($outsider->id, $ids);
    }

    public function test_roleless_user_cannot_assign_roles(): void
    {
        $tenant = $this->postJson('/api/v1/tenants', ['name' => 'NoPerm GmbH'])->json('id');
        $this->actingAsUser();
        $target = User::factory()->create();

        $this->putJson("/api/v1/users/{$target->id}/roles", ['roles' => ['auditor']], ['X-Tenant' => $tenant])
            ->assertForbidden();
    }

    protected function actingAsUser(Tenant|string|null $tenant = null): User
    {
        $user = User::factory()->create();
        if ($tenant) {
            tenancy()->initialize($tenant);
            $user->assignRole('administrator');
        }
        Sanctum::actingAs($user->fresh());

        return $user;
    }
}
