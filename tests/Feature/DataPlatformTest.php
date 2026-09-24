<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DataPlatformTest extends TestCase
{
    use RefreshDatabase;

    protected function acting(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_domain_events_are_recorded_in_event_store(): void
    {
        $tenant = Tenant::create(['name' => 'Ev GmbH']);
        $this->acting();

        $this->postJson('/api/v1/companies', ['name' => 'EvCo'], ['X-Tenant' => $tenant->id])
            ->assertCreated();

        $row = DB::table('stored_events')->latest('id')->first();
        $props = json_decode($row->event_properties, true);
        $meta = json_decode($row->meta_data, true);

        $this->assertSame('company.created', $props['type']);
        $this->assertSame($tenant->getTenantKey(), $meta['tenant_id']);

        $this->getJson('/api/v1/events', ['X-Tenant' => $tenant->id])
            ->assertOk()->assertJsonPath('data.0.event_properties.type', 'company.created');
    }

    public function test_events_endpoint_is_tenant_scoped(): void
    {
        $tenantA = Tenant::create(['name' => 'EA GmbH']);
        $tenantB = Tenant::create(['name' => 'EB GmbH']);
        $this->acting();

        $this->postJson('/api/v1/companies', ['name' => 'OnlyA'], ['X-Tenant' => $tenantA->id]);

        $res = $this->getJson('/api/v1/events', ['X-Tenant' => $tenantB->id])->assertOk();

        $this->assertCount(0, $res->json('data'));
    }

    public function test_metrics_aggregation(): void
    {
        $tenant = Tenant::create(['name' => 'Kpi GmbH']);
        $this->acting();

        $this->postJson('/api/v1/companies', ['name' => 'C1'], ['X-Tenant' => $tenant->id]);
        $this->postJson('/api/v1/companies', ['name' => 'C2'], ['X-Tenant' => $tenant->id]);
        $this->postJson('/api/v1/tasks', ['title' => 'T1'], ['X-Tenant' => $tenant->id]);

        tenancy()->initialize($tenant);
        Artisan::call('analytics:aggregate');

        $res = $this->getJson('/api/v1/metrics', ['X-Tenant' => $tenant->id])->assertOk();

        $this->assertEquals('2.0000', $res->json('companies.value'));
        $this->assertEquals('1.0000', $res->json('tasks.value'));

        $this->getJson('/api/v1/metrics/companies', ['X-Tenant' => $tenant->id])
            ->assertOk()->assertJsonCount(1);
    }
}
