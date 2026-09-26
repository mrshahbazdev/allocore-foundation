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

        $this->postJson('/api/v1/risk-assessments', [
            'title' => 'GB Review bald', 'risk_level' => 'low', 'review_at' => now()->addDays(5)->toISOString(),
        ], ['X-Tenant' => $tenant->id]);

        $person = $this->postJson('/api/v1/persons', [
            'first_name' => 'Anna', 'last_name' => 'Urlaub',
        ], ['X-Tenant' => $tenant->id]);
        $leave = $this->postJson('/api/v1/leave-requests', [
            'person_id' => $person->json('id'),
            'type' => 'vacation', 'starts_on' => now()->addDays(10)->toDateString(), 'ends_on' => now()->addDays(12)->toDateString(),
        ], ['X-Tenant' => $tenant->id]);
        if ($leave->status() === 201) {
            DB::table('leave_requests')->where('id', $leave->json('id'))
                ->update(['created_at' => now()->subDays(8)]);
        }

        $this->postJson('/api/v1/audits', [
            'title' => 'Überfälliges Audit', 'ends_on' => now()->subDay()->toDateString(),
        ], ['X-Tenant' => $tenant->id]);

        $starting = $this->postJson('/api/v1/audits', [
            'title' => 'Audit bald', 'starts_on' => now()->addDays(4)->toDateString(),
        ], ['X-Tenant' => $tenant->id]);

        $this->postJson('/api/v1/audit-findings', [
            'audit_id' => $starting['id'], 'title' => 'Bald fällig', 'due_at' => now()->addDays(3)->toDateString(),
        ], ['X-Tenant' => $tenant->id]);

        $machine = $this->postJson('/api/v1/machines', ['name' => 'Fräse 1'], ['X-Tenant' => $tenant->id])->json();
        $this->postJson('/api/v1/production-orders', [
            'order_no' => 'AUF-1', 'product' => 'Krone', 'quantity' => 5, 'machine_id' => $machine['id'],
        ], ['X-Tenant' => $tenant->id]);

        $codes = collect($this->getJson('/api/v1/insights', ['X-Tenant' => $tenant->id])->json())
            ->pluck('code')->all();

        $this->assertContains('tasks_overdue', $codes);
        $this->assertContains('high_risks_open', $codes);
        $this->assertContains('risks_no_measures', $codes);
        $this->assertContains('audits_overdue', $codes);
        $this->assertContains('audits_starting_soon', $codes);
        $this->assertContains('audit_findings_due_soon', $codes);
        $this->assertContains('audit_findings_unassigned', $codes);
        $this->assertContains('audits_unassigned', $codes);
        $this->assertContains('audits_no_auditor', $codes);
        $this->assertContains('orders_unassigned', $codes);
        $this->assertContains('risk_reviews_due_soon', $codes);
        $this->assertContains('leave_requests_pending', $codes);
        $this->assertContains('leave_pending_stale', $codes);
    }

    public function test_insights_reports_negative_liquidity(): void
    {
        $tenant = Tenant::create(['name' => 'Fin GmbH']);
        $user = User::factory()->create();
        tenancy()->initialize($tenant);
        $user->assignRole('holding');
        Sanctum::actingAs($user->fresh());
        tenancy()->end();

        $company = $this->postJson('/api/v1/companies', ['name' => 'FinCo'], ['X-Tenant' => $tenant->id])->assertCreated()->json();
        $this->postJson('/api/v1/financial-reports', [
            'company_id' => $company['id'],
            'period' => now()->format('Y-m'),
            'liquidity' => -1500,
        ], ['X-Tenant' => $tenant->id])->assertCreated();

        $codes = collect($this->getJson('/api/v1/insights', ['X-Tenant' => $tenant->id])->json())
            ->pluck('code')->all();

        $this->assertContains('fin_negative_liquidity', $codes);
    }

    public function test_insights_reports_projects_overdue_and_tenders_deadline_soon(): void
    {
        $tenant = Tenant::create(['name' => 'ProjektAusschreibung GmbH']);
        $user = User::factory()->create();
        tenancy()->initialize($tenant);
        $user->assignRole('holding');
        Sanctum::actingAs($user->fresh());
        tenancy()->end();

        $this->postJson('/api/v1/projects', [
            'name' => 'Überfälliges Projekt', 'status' => 'active',
            'starts_at' => now()->subDays(30)->toDateString(),
            'ends_at' => now()->subDays(2)->toDateString(),
        ], ['X-Tenant' => $tenant->id])->assertCreated();

        $this->postJson('/api/v1/tenders', [
            'title' => 'Pramoterin gesucht', 'deadline_at' => now()->addDays(4)->toISOString(),
        ], ['X-Tenant' => $tenant->id])->assertCreated();

        $codes = collect($this->getJson('/api/v1/insights', ['X-Tenant' => $tenant->id])->json())
            ->pluck('code')->all();

        $this->assertContains('projects_overdue', $codes);
        $this->assertContains('tenders_deadline_soon', $codes);
    }

    public function test_insights_reports_questions_open_and_leave_active_today(): void
    {
        $tenant = Tenant::create(['name' => 'Netzwerk GmbH']);
        $user = User::factory()->create();
        tenancy()->initialize($tenant);
        $user->assignRole('holding');
        Sanctum::actingAs($user->fresh());
        tenancy()->end();

        $this->postJson('/api/v1/questions', ['title' => 'Wie kalibriert man?'], ['X-Tenant' => $tenant->id])->assertCreated();

        $person = $this->postJson('/api/v1/persons', ['first_name' => 'Lea', 'last_name' => 'Fern'], ['X-Tenant' => $tenant->id])->assertCreated()->json();
        $leave = $this->postJson('/api/v1/leave-requests', [
            'person_id' => $person['id'], 'type' => 'vacation',
            'starts_on' => now()->subDay()->toDateString(),
            'ends_on' => now()->addDay()->toDateString(),
        ], ['X-Tenant' => $tenant->id])->assertCreated()->json();
        $this->putJson("/api/v1/leave-requests/{$leave['id']}", ['status' => 'approved'], ['X-Tenant' => $tenant->id])->assertOk();

        $codes = collect($this->getJson('/api/v1/insights', ['X-Tenant' => $tenant->id])->json())
            ->pluck('code')->all();

        $this->assertContains('questions_open', $codes);
        $this->assertContains('leave_active_today', $codes);
    }

    public function test_insights_reports_strategies_overdue_and_tenders_overdue(): void
    {
        $tenant = Tenant::create(['name' => 'StrategyTender GmbH']);
        $user = User::factory()->create();
        tenancy()->initialize($tenant);
        $user->assignRole('holding');
        Sanctum::actingAs($user->fresh());
        tenancy()->end();

        $this->postJson('/api/v1/strategies', [
            'name' => 'Veraltete Strategie', 'status' => 'active',
            'starts_at' => now()->subDays(60)->toDateString(),
            'ends_at' => now()->subDays(3)->toDateString(),
        ], ['X-Tenant' => $tenant->id])->assertCreated();

        $this->postJson('/api/v1/tenders', [
            'title' => 'Überfällige Ausschreibung', 'deadline_at' => now()->subDays(2)->toISOString(),
        ], ['X-Tenant' => $tenant->id])->assertCreated();

        $codes = collect($this->getJson('/api/v1/insights', ['X-Tenant' => $tenant->id])->json())
            ->pluck('code')->all();

        $this->assertContains('strategies_overdue', $codes);
        $this->assertContains('tenders_overdue', $codes);
    }

    public function test_insights_reports_orders_overdue_and_measures_due_soon(): void
    {
        $tenant = Tenant::create(['name' => 'Produktion GmbH']);
        $user = User::factory()->create();
        tenancy()->initialize($tenant);
        $user->assignRole('holding');
        Sanctum::actingAs($user->fresh());
        tenancy()->end();

        $this->postJson('/api/v1/production-orders', [
            'order_no' => 'PO-OVER', 'product' => 'Brücke 4er',
            'status' => 'running', 'due_at' => now()->subDays(2)->toISOString(),
        ], ['X-Tenant' => $tenant->id])->assertCreated();

        $project = $this->postJson('/api/v1/projects', ['name' => 'Qualität', 'status' => 'active'], ['X-Tenant' => $tenant->id])->assertCreated()->json();
        $this->postJson('/api/v1/measures', [
            'title' => 'Sicherheitsprüfung', 'project_id' => $project['id'],
            'due_at' => now()->addDays(4)->toISOString(),
        ], ['X-Tenant' => $tenant->id])->assertCreated();

        $codes = collect($this->getJson('/api/v1/insights', ['X-Tenant' => $tenant->id])->json())
            ->pluck('code')->all();

        $this->assertContains('orders_overdue', $codes);
        $this->assertContains('measures_due_soon', $codes);
    }

    public function test_insights_reports_inspections_and_instructions(): void
    {
        $tenant = Tenant::create(['name' => 'Compliance GmbH']);
        $user = User::factory()->create();
        tenancy()->initialize($tenant);
        $user->assignRole('holding');
        Sanctum::actingAs($user->fresh());
        tenancy()->end();

        $this->postJson('/api/v1/inspections', [
            'title' => 'Alte Prüfung', 'status' => 'scheduled',
            'scheduled_at' => now()->subDays(3)->toISOString(),
        ], ['X-Tenant' => $tenant->id])->assertCreated();
        $this->postJson('/api/v1/inspections', [
            'title' => 'Baldige Prüfung', 'status' => 'scheduled', 'responsible_id' => $user->id,
            'scheduled_at' => now()->addDays(4)->toISOString(),
        ], ['X-Tenant' => $tenant->id])->assertCreated();

        $this->postJson('/api/v1/instructions', [
            'title' => 'Sicherheitsunterweisung', 'due_at' => now()->addDays(3)->toISOString(),
        ], ['X-Tenant' => $tenant->id])->assertCreated();
        $this->postJson('/api/v1/instructions', [
            'title' => 'Überfällige Unterweisung', 'due_at' => now()->subDays(2)->toISOString(),
        ], ['X-Tenant' => $tenant->id])->assertCreated();

        $codes = collect($this->getJson('/api/v1/insights', ['X-Tenant' => $tenant->id])->json())
            ->pluck('code')->all();

        $this->assertContains('inspections_overdue', $codes);
        $this->assertContains('inspections_due_soon', $codes);
        $this->assertContains('instructions_due_soon', $codes);
        $this->assertContains('instructions_overdue', $codes);
    }

    public function test_insights_reports_deadlines_and_tasks_due(): void
    {
        $tenant = Tenant::create(['name' => 'Fristen GmbH']);
        $user = User::factory()->create();
        tenancy()->initialize($tenant);
        $user->assignRole('holding');
        Sanctum::actingAs($user->fresh());
        tenancy()->end();

        $this->postJson('/api/v1/deadlines', [
            'title' => 'Verstrichene Frist', 'due_at' => now()->subDays(2)->toISOString(),
        ], ['X-Tenant' => $tenant->id])->assertCreated();
        $this->postJson('/api/v1/deadlines', [
            'title' => 'Baldige Frist', 'due_at' => now()->addDays(4)->toISOString(),
        ], ['X-Tenant' => $tenant->id])->assertCreated();
        $this->postJson('/api/v1/tasks', [
            'title' => 'Fällige Aufgabe', 'due_at' => now()->addDays(3)->toISOString(),
        ], ['X-Tenant' => $tenant->id])->assertCreated();

        $codes = collect($this->getJson('/api/v1/insights', ['X-Tenant' => $tenant->id])->json())
            ->pluck('code')->all();

        $this->assertContains('deadlines_overdue', $codes);
        $this->assertContains('deadlines_due_soon', $codes);
        $this->assertContains('tasks_due_soon', $codes);
    }

    public function test_insights_reports_investments_drawdown_and_capital_need(): void
    {
        $tenant = Tenant::create(['name' => 'Kapital GmbH']);
        $user = User::factory()->create();
        tenancy()->initialize($tenant);
        $user->assignRole('holding');
        Sanctum::actingAs($user->fresh());
        tenancy()->end();

        $portfolio = $this->postJson('/api/v1/portfolios', ['name' => 'Hauptportfolio'], ['X-Tenant' => $tenant->id])->assertCreated()->json();
        $this->postJson('/api/v1/investments', [
            'portfolio_id' => $portfolio['id'], 'name' => 'Verlustposition',
            'cost_basis' => 10000, 'current_value' => 8000,
        ], ['X-Tenant' => $tenant->id])->assertCreated();

        $this->postJson('/api/v1/participations', [
            'name' => 'Beteiligung AG', 'stake_pct' => 25, 'capital_need' => 5000, 'status' => 'active',
        ], ['X-Tenant' => $tenant->id])->assertCreated();

        $codes = collect($this->getJson('/api/v1/insights', ['X-Tenant' => $tenant->id])->json())
            ->pluck('code')->all();

        $this->assertContains('investments_drawdown', $codes);
        $this->assertContains('participations_capital_need', $codes);
    }

    public function test_insights_reports_machines_and_risk_reviews(): void
    {
        $tenant = Tenant::create(['name' => 'Maschinen GmbH']);
        $user = User::factory()->create();
        tenancy()->initialize($tenant);
        $user->assignRole('holding');
        Sanctum::actingAs($user->fresh());
        tenancy()->end();

        $this->postJson('/api/v1/machines', ['name' => 'Fräse 1', 'status' => 'maintenance'], ['X-Tenant' => $tenant->id])->assertCreated();
        $this->postJson('/api/v1/machines', ['name' => 'Drucker 1', 'status' => 'active'], ['X-Tenant' => $tenant->id])->assertCreated();
        $this->postJson('/api/v1/risk-assessments', [
            'title' => 'GB Galvanik', 'review_at' => now()->subDays(5)->toISOString(), 'status' => 'open',
        ], ['X-Tenant' => $tenant->id])->assertCreated();

        $codes = collect($this->getJson('/api/v1/insights', ['X-Tenant' => $tenant->id])->json())
            ->pluck('code')->all();

        $this->assertContains('machines_in_maintenance', $codes);
        $this->assertContains('machines_idle', $codes);
        $this->assertContains('risk_reviews_overdue', $codes);
    }

    public function test_every_insight_code_is_wired_in_workspace_and_docs(): void
    {
        $controller = file_get_contents(base_path('Modules/DataPlatform/app/Http/Controllers/InsightController.php'));
        preg_match_all("/hit\\('(?:critical|warning|info)',\\s*'([a-z0-9_]+)'/", $controller, $m);
        $codes = array_diff(array_unique($m[1]), ['all_clear']);
        $this->assertNotEmpty($codes);

        $workspace = file_get_contents(resource_path('views/workspace.blade.php'));
        $docs = file_get_contents(base_path('docs/API_REFERENCE.md'));

        preg_match('/insightSection\\(code\\).*?\\}\\)\\[code\\]/s', $workspace, $secMatch);
        preg_match('/insightFilter\\(code\\).*?\\}\\)\\[code\\]/s', $workspace, $filterMatch);
        $sections = $secMatch[0] ?? '';
        $filters = $filterMatch[0] ?? '';

        foreach ($codes as $code) {
            $this->assertStringContainsString("`{$code}`", $docs, "insight {$code} fehlt in docs/API_REFERENCE.md");
            $this->assertStringContainsString("{$code}:'", $sections, "insight {$code} fehlt in insightSection");
            $this->assertStringContainsString("{$code}:", $filters, "insight {$code} fehlt in insightFilter");
        }
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
