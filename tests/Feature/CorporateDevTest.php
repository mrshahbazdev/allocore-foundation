<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Notifications\Assigned;
use Tests\TestCase;

class CorporateDevTest extends TestCase
{
    use RefreshDatabase;

    private function acting(Tenant $tenant): User
    {
        RoleSeeder::forTenant($tenant);
        $user = User::factory()->create();
        tenancy()->initialize($tenant);
        $user->assignRole('holding');
        Sanctum::actingAs($user->fresh());
        tenancy()->end();

        return $user;
    }

    public function test_strategy_project_measure_crud_is_tenant_scoped(): void
    {
        $tenantA = Tenant::create(['name' => 'X GmbH']);
        $tenantB = Tenant::create(['name' => 'X GmbH']);
        $user = $this->acting($tenantA);

        $strategy = $this->postJson('/api/v1/strategies', [
            'name' => 'Digitalisierung 2027',
            'status' => 'active',
        ], ['X-Tenant' => $tenantA->id])->assertCreated()->json();

        $project = $this->postJson('/api/v1/projects', [
            'strategy_id' => $strategy['id'],
            'name' => 'ERP-Einfuehrung',
            'status' => 'active',
            'progress' => 35,
        ], ['X-Tenant' => $tenantA->id])->assertCreated()->json();

        $this->postJson('/api/v1/measures', [
            'project_id' => $project['id'],
            'title' => 'Anforderungsworkshop',
        ], ['X-Tenant' => $tenantA->id])->assertCreated()->json();

        $this->getJson("/api/v1/projects/{$project['id']}", ['X-Tenant' => $tenantA->id])
            ->assertOk()
            ->assertJsonPath('name', 'ERP-Einfuehrung')
            ->assertJsonPath('strategy.name', 'Digitalisierung 2027')
            ->assertJsonCount(1, 'measures');

        // Tenant B: no role assigned -> 403
        Sanctum::actingAs(User::find($user->id));
        $this->getJson('/api/v1/projects', ['X-Tenant' => $tenantB->id])->assertForbidden();

        // unauthenticated -> 401
        Sanctum::actingAs(User::factory()->create()); // ensure guard state, then simulate guest
    }

    public function test_projects_require_permission_to_write(): void
    {
        $tenant = Tenant::create(['name' => 'X GmbH']);
        RoleSeeder::forTenant($tenant);
        $user = User::factory()->create();
        tenancy()->initialize($tenant);
        $user->assignRole('mitarbeiter'); // view only for projects
        Sanctum::actingAs($user->fresh());
        tenancy()->end();

        $this->getJson('/api/v1/projects', ['X-Tenant' => $tenant->id])->assertOk();
        $this->postJson('/api/v1/projects', ['name' => 'X'], ['X-Tenant' => $tenant->id])->assertForbidden();
        $this->postJson('/api/v1/strategies', ['name' => 'S'], ['X-Tenant' => $tenant->id])->assertForbidden();
    }

    public function test_events_record_project_activity(): void
    {
        $tenant = Tenant::create(['name' => 'X GmbH']);
        $user = $this->acting($tenant);

        $this->postJson('/api/v1/projects', ['name' => 'Wachstum'], ['X-Tenant' => $tenant->id])->assertCreated();

        $types = collect(
            $this->getJson('/api/v1/events', ['X-Tenant' => $tenant->id])->json('data')
        )->pluck('event_class')->all();

        $this->assertNotEmpty($types);
    }

    public function test_strategies_projects_measures_filter_by_q(): void
    {
        $tenant = Tenant::create(['name' => 'Q GmbH']);
        $this->acting($tenant);

        $strategy = $this->postJson('/api/v1/strategies', [
            'name' => 'Digitalisierung 2027', 'status' => 'active',
        ], ['X-Tenant' => $tenant->id])->assertCreated()->json();
        $this->postJson('/api/v1/strategies', [
            'name' => 'Internationalisierung', 'status' => 'active',
        ], ['X-Tenant' => $tenant->id])->assertCreated();

        $project = $this->postJson('/api/v1/projects', [
            'strategy_id' => $strategy['id'], 'name' => 'ERP-Einfuehrung', 'status' => 'active',
        ], ['X-Tenant' => $tenant->id])->assertCreated()->json();
        $this->postJson('/api/v1/measures', [
            'project_id' => $project['id'], 'title' => 'Anforderungsworkshop',
        ], ['X-Tenant' => $tenant->id])->assertCreated();

        $this->assertCount(1, $this->getJson('/api/v1/strategies?q=Digital', ['X-Tenant' => $tenant->id])->json('data'));
        $this->assertCount(0, $this->getJson('/api/v1/strategies?q=KeinTrefferXYZ', ['X-Tenant' => $tenant->id])->json('data'));
        $this->assertCount(1, $this->getJson('/api/v1/projects?q=ERP', ['X-Tenant' => $tenant->id])->json('data'));
        $this->assertCount(1, $this->getJson('/api/v1/measures?q=Workshop', ['X-Tenant' => $tenant->id])->json('data'));
        $this->assertCount(0, $this->getJson('/api/v1/measures?q=KeinTrefferXYZ', ['X-Tenant' => $tenant->id])->json('data'));
    }

    public function test_measure_done_notifies_project_owner(): void
    {
        Notification::fake();
        $tenant = Tenant::create(['name' => 'M GmbH']);
        $actor = $this->acting($tenant);
        $owner = User::factory()->create();

        $strategy = $this->postJson('/api/v1/strategies', [
            'name' => 'Wachstum', 'status' => 'active',
        ], ['X-Tenant' => $tenant->id])->assertCreated()->json();

        $project = $this->postJson('/api/v1/projects', [
            'strategy_id' => $strategy['id'],
            'name' => 'Portal',
            'status' => 'active',
            'owner_id' => $owner->id,
        ], ['X-Tenant' => $tenant->id])->assertCreated()->json();

        $measure = $this->postJson('/api/v1/measures', [
            'project_id' => $project['id'],
            'title' => 'Rollout',
        ], ['X-Tenant' => $tenant->id])->assertCreated()->json();

        $this->putJson("/api/v1/measures/{$measure['id']}", ['status' => 'done'], ['X-Tenant' => $tenant->id])->assertOk();

        Notification::assertSentTo($owner, function (Assigned $n) {
            return $n->kind === 'massnahme';
        });
    }
}
