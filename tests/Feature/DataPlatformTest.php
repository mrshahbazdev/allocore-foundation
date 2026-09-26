<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Models\Company;
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

    public function test_events_endpoint_caps_per_page(): void
    {
        $tenant = Tenant::create(['name' => 'Cap GmbH']);
        $this->acting($tenant);

        $this->getJson('/api/v1/events?per_page=999', ['X-Tenant' => $tenant->id])
            ->assertOk()->assertJsonPath('per_page', 200);
    }

    public function test_metrics_aggregation(): void
    {
        $tenant = Tenant::create(['name' => 'Kpi GmbH']);
        $this->acting($tenant);

        $this->postJson('/api/v1/companies', ['name' => 'C1'], ['X-Tenant' => $tenant->id]);
        $this->postJson('/api/v1/companies', ['name' => 'C2'], ['X-Tenant' => $tenant->id]);
        $this->postJson('/api/v1/tasks', ['title' => 'T1'], ['X-Tenant' => $tenant->id]);

        $companyId = Company::where('name', 'C1')->value('id');
        $this->postJson('/api/v1/financial-reports', [
            'company_id' => $companyId,
            'period' => now()->format('Y-m'),
            'revenue' => 12500.5,
            'ebitda' => 3100,
        ], ['X-Tenant' => $tenant->id]);

        tenancy()->initialize($tenant);
        Artisan::call('analytics:aggregate');

        $res = $this->getJson('/api/v1/metrics', ['X-Tenant' => $tenant->id])->assertOk();

        $this->assertEquals('2.0000', $res->json('companies.value'));
        $this->assertEquals('1.0000', $res->json('tasks.value'));
        $this->assertEquals('12500.5000', $res->json('fin_revenue.value'));
        $this->assertEquals('3100.0000', $res->json('fin_ebitda.value'));

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

        $this->postJson('/api/v1/audits', [
            'title' => 'Überfälliges Audit', 'ends_on' => now()->subDay()->toDateString(),
        ], ['X-Tenant' => $tenant->id]);

        $starting = $this->postJson('/api/v1/audits', [
            'title' => 'Audit bald', 'starts_on' => now()->addDays(4)->toDateString(),
        ], ['X-Tenant' => $tenant->id]);

        $this->postJson('/api/v1/audit-findings', [
            'audit_id' => $starting['id'], 'title' => 'Bald fällig', 'due_at' => now()->addDays(3)->toDateString(),
        ], ['X-Tenant' => $tenant->id]);

        $codes = collect($this->getJson('/api/v1/insights', ['X-Tenant' => $tenant->id])->json())
            ->pluck('code')->all();

        $this->assertContains('tasks_overdue', $codes);
        $this->assertContains('high_risks_open', $codes);
        $this->assertContains('audits_overdue', $codes);
        $this->assertContains('audits_starting_soon', $codes);
        $this->assertContains('audit_findings_due_soon', $codes);
        $this->assertContains('audit_findings_unassigned', $codes);
        $this->assertContains('audits_unassigned', $codes);
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

    public function test_global_search_finds_across_sections(): void
    {
        $tenant = Tenant::create(['name' => 'S GmbH']);
        $this->acting($tenant);

        $this->postJson('/api/v1/companies', ['name' => 'Acme Zahn GmbH'], ['X-Tenant' => $tenant->id]);
        $this->postJson('/api/v1/tasks', ['title' => 'Acme Bericht prüfen'], ['X-Tenant' => $tenant->id]);
        DB::table('data_objects')->insert(['tenant_id' => $tenant->id, 'name' => 'Acme Vertrag.pdf', 'path' => 'acme/vertrag.pdf']);
        DB::table('graph_entities')->insert(['tenant_id' => $tenant->id, 'type' => 'company', 'name' => 'Acme Zahn GmbH']);

        $res = $this->getJson('/api/v1/search?q=Acme', ['X-Tenant' => $tenant->id])->assertOk()->json();
        $sections = collect($res)->pluck('section')->all();
        $this->assertContains('companies', $sections);
        $this->assertContains('tasks', $sections);
        $this->assertContains('data-objects', $sections);
        $this->assertContains('graph-entities', $sections);

        $res = $this->getJson('/api/v1/search?q=Acme&sections=tasks', ['X-Tenant' => $tenant->id])->assertOk()->json();
        $this->assertNotEmpty($res);
        $this->assertSame(['tasks'], array_unique(collect($res)->pluck('section')->all()));

        $other = Tenant::create(['name' => 'Fremd GmbH']);
        $fremdId = DB::table('graph_entities')->insertGetId(['tenant_id' => $other->id, 'type' => 'company', 'name' => 'Acme Fremd GmbH']);
        $fremdObj = DB::table('data_objects')->insertGetId(['tenant_id' => $other->id, 'name' => 'Acme Fremd.pdf', 'path' => 'fremd.pdf']);
        $res = $this->getJson('/api/v1/search?q=Acme', ['X-Tenant' => $tenant->id])->assertOk()->json();
        $ids = collect($res)->pluck('id')->all();
        $this->assertNotContains($fremdId, $ids);
        $this->assertNotContains($fremdObj, $ids);

        $this->getJson('/api/v1/search?q=a', ['X-Tenant' => $tenant->id])
            ->assertOk()->assertExactJson([]);
    }

    public function test_notifications_endpoint_lists_and_marks_read(): void
    {
        $tenant = Tenant::create(['name' => 'N GmbH']);
        $user = $this->acting($tenant);

        $notif = new class extends Notification
        {
            public function via($n)
            {
                return ['database'];
            }

            public function toArray($n)
            {
                return ['title' => 'Test', 'kind' => 'frist', 'due_at' => '01.01.2027'];
            }
        };
        $user->notify($notif);
        $other = User::factory()->create();
        $other->notify($notif);

        $res = $this->getJson('/api/v1/notifications', ['X-Tenant' => $tenant->id])->assertOk()->json();
        $this->assertCount(1, $res);
        $this->assertFalse($res[0]['read']);

        $this->postJson('/api/v1/notifications/'.$res[0]['id'].'/read', [], ['X-Tenant' => $tenant->id])->assertOk();
        $res = $this->getJson('/api/v1/notifications', ['X-Tenant' => $tenant->id])->assertOk()->json();
        $this->assertTrue($res[0]['read']);
    }
}
