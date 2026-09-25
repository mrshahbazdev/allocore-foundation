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

    protected function acting(Tenant $tenant): User
    {
        $user = User::factory()->create();
        tenancy()->initialize($tenant);
        $user->assignRole('holding');
        Sanctum::actingAs($user->fresh());

        return $user;
    }

    public function test_domain_events_are_recorded_in_event_store(): void
    {
        $tenant = Tenant::create(['name' => 'Ev GmbH']);
        $this->acting($tenant);

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

    public function test_events_endpoint_filters_by_subject_id(): void
    {
        $tenant = Tenant::create(['name' => 'Sub GmbH']);
        $this->acting($tenant);

        $this->postJson('/api/v1/companies', ['name' => 'SubA'], ['X-Tenant' => $tenant->id]);
        $this->postJson('/api/v1/companies', ['name' => 'SubB'], ['X-Tenant' => $tenant->id]);

        $subjectId = DB::table('companies')->where('name', 'SubA')->value('id');

        $res = $this->getJson('/api/v1/events?subject_id='.$subjectId, ['X-Tenant' => $tenant->id])
            ->assertOk();

        $this->assertNotEmpty($res->json('data'));
        foreach ($res->json('data') as $row) {
            $this->assertSame($subjectId, $row['event_properties']['subject']['id']);
        }
    }

    public function test_events_endpoint_is_tenant_scoped(): void
    {
        $tenantA = Tenant::create(['name' => 'EA GmbH']);
        $tenantB = Tenant::create(['name' => 'EB GmbH']);
        $user = $this->acting($tenantA);

        $this->postJson('/api/v1/companies', ['name' => 'OnlyA'], ['X-Tenant' => $tenantA->id]);

        Sanctum::actingAs(User::find($user->id));
        $this->getJson('/api/v1/events', ['X-Tenant' => $tenantB->id])->assertForbidden();
    }

    public function test_metrics_aggregation(): void
    {
        $tenant = Tenant::create(['name' => 'Kpi GmbH']);
        $this->acting($tenant);

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

    public function test_insights_endpoint_reports_findings(): void
    {
        $tenant = Tenant::create(['name' => 'Insights GmbH']);
        $user = User::factory()->create();
        tenancy()->initialize($tenant);
        $user->assignRole('holding');
        Sanctum::actingAs($user->fresh());
        tenancy()->end();

        $this->postJson('/api/v1/tasks', [
            'title' => 'Alt', 'due_at' => now()->subDays(2),
        ], ['X-Tenant' => $tenant->id]);

        $this->postJson('/api/v1/risk-assessments', [
            'title' => 'Laser-Arbeitsplatz', 'risk_level' => 'high',
        ], ['X-Tenant' => $tenant->id]);

        $codes = collect($this->getJson('/api/v1/insights', ['X-Tenant' => $tenant->id])->json())
            ->pluck('code')->all();

        $this->assertContains('tasks_overdue', $codes);
        $this->assertContains('high_risks_open', $codes);
    }

    public function test_nav_counts_returns_overdue_and_today(): void
    {
        $tenant = Tenant::create(['name' => 'Nav GmbH']);
        $this->acting($tenant);

        $this->postJson('/api/v1/tasks', ['title' => 'Alt', 'due_at' => now()->subDays(3)], ['X-Tenant' => $tenant->id]);
        $this->postJson('/api/v1/tasks', ['title' => 'Heute', 'due_at' => now()], ['X-Tenant' => $tenant->id]);
        $this->postJson('/api/v1/tasks', ['title' => 'Später', 'due_at' => now()->addDays(5)], ['X-Tenant' => $tenant->id]);
        $this->postJson('/api/v1/tasks', ['title' => 'Fertig', 'due_at' => now()->subDays(3), 'status' => 'done'], ['X-Tenant' => $tenant->id]);

        $res = $this->getJson('/api/v1/nav-counts', ['X-Tenant' => $tenant->id])->assertOk()->json();

        $this->assertSame(1, $res['tasks'][0]);
        $this->assertSame(1, $res['tasks'][1]);
        $this->assertArrayNotHasKey('events', $res);
    }
}
