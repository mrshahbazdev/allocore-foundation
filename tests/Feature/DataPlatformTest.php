<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\Ai\Models\AiAnalysis;
use Modules\Compliance\Models\Deadline;
use Modules\Compliance\Models\Instruction;
use Modules\Core\Models\Company;
use Modules\CorporateDev\Models\Project;
use Modules\DataLake\Models\DataObject;
use Modules\Documents\Models\Document;
use Modules\ExpertNetwork\Models\Answer;
use Modules\ExpertNetwork\Models\Question;
use Modules\ExpertNetwork\Models\TenderApplication;
use Modules\Hr\Models\LeaveRequest;
use Modules\Investments\Models\Investment;
use Modules\KnowledgeGraph\Models\GraphEdge;
use Modules\Production\Models\Machine;
use Modules\Production\Models\ProductionOrder;
use Modules\Tasks\Models\Task;
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

    public function test_insights_reports_unassigned_records(): void
    {
        $tenant = Tenant::create(['name' => 'Verantwortung GmbH']);
        $user = User::factory()->create();
        tenancy()->initialize($tenant);
        $user->assignRole('holding');
        Sanctum::actingAs($user->fresh());
        tenancy()->end();

        $this->postJson('/api/v1/tasks', ['title' => 'Ohne Assignee'], ['X-Tenant' => $tenant->id])->assertCreated();
        $this->postJson('/api/v1/deadlines', ['title' => 'Ohne Responsible', 'due_at' => now()->addDays(30)->toISOString()], ['X-Tenant' => $tenant->id])->assertCreated();
        $this->postJson('/api/v1/instructions', ['title' => 'Ohne Verantwortliche'], ['X-Tenant' => $tenant->id])->assertCreated();
        $this->postJson('/api/v1/projects', ['name' => 'Ohne Owner'], ['X-Tenant' => $tenant->id])->assertCreated();

        $codes = collect($this->getJson('/api/v1/insights', ['X-Tenant' => $tenant->id])->json())
            ->pluck('code')->all();

        $this->assertContains('tasks_unassigned', $codes);
        $this->assertContains('deadlines_unassigned', $codes);
        $this->assertContains('instructions_unassigned', $codes);
        $this->assertContains('projects_unassigned', $codes);
    }

    public function test_insights_reports_unassigned_measures_and_inspections(): void
    {
        $tenant = Tenant::create(['name' => 'Unzuständig GmbH']);
        $user = User::factory()->create();
        tenancy()->initialize($tenant);
        $user->assignRole('holding');
        Sanctum::actingAs($user->fresh());
        tenancy()->end();

        $project = $this->postJson('/api/v1/projects', ['name' => 'PSA'], ['X-Tenant' => $tenant->id])->assertCreated()->json();
        $this->postJson('/api/v1/measures', ['title' => 'Ohne Responsible', 'project_id' => $project['id']], ['X-Tenant' => $tenant->id])->assertCreated();
        $this->postJson('/api/v1/inspections', ['title' => 'Ohne Responsible'], ['X-Tenant' => $tenant->id])->assertCreated();
        $this->postJson('/api/v1/questions', ['title' => 'Ohne Zuständige'], ['X-Tenant' => $tenant->id])->assertCreated();

        $codes = collect($this->getJson('/api/v1/insights', ['X-Tenant' => $tenant->id])->json())
            ->pluck('code')->all();

        $this->assertContains('measures_unassigned', $codes);
        $this->assertContains('inspections_unassigned', $codes);
        $this->assertContains('questions_unassigned', $codes);
    }

    public function test_insights_reports_missing_reports_and_overlapping_leave(): void
    {
        $tenant = Tenant::create(['name' => 'Lücken GmbH']);
        $user = User::factory()->create();
        tenancy()->initialize($tenant);
        $user->assignRole('holding');
        Sanctum::actingAs($user->fresh());
        tenancy()->end();

        $this->postJson('/api/v1/companies', ['name' => 'Ohne Bericht AG'], ['X-Tenant' => $tenant->id])->assertCreated();
        $this->postJson('/api/v1/tenders', ['title' => 'Ohne Bewerbung'], ['X-Tenant' => $tenant->id])->assertCreated();

        $person = $this->postJson('/api/v1/persons', ['first_name' => 'Lea', 'last_name' => 'Fern'], ['X-Tenant' => $tenant->id])->assertCreated()->json();
        $a = $this->postJson('/api/v1/leave-requests', ['person_id' => $person['id'], 'type' => 'vacation', 'starts_on' => now()->addDay()->toDateString(), 'ends_on' => now()->addDays(5)->toDateString()], ['X-Tenant' => $tenant->id])->assertCreated()->json();
        $b = $this->postJson('/api/v1/leave-requests', ['person_id' => $person['id'], 'type' => 'vacation', 'starts_on' => now()->addDays(3)->toDateString(), 'ends_on' => now()->addDays(8)->toDateString()], ['X-Tenant' => $tenant->id])->assertCreated()->json();
        $this->putJson("/api/v1/leave-requests/{$a['id']}", ['status' => 'approved'], ['X-Tenant' => $tenant->id])->assertOk();
        $this->putJson("/api/v1/leave-requests/{$b['id']}", ['status' => 'approved'], ['X-Tenant' => $tenant->id])->assertOk();

        $codes = collect($this->getJson('/api/v1/insights', ['X-Tenant' => $tenant->id])->json())
            ->pluck('code')->all();

        $this->assertContains('fin_reports_missing', $codes);
        $this->assertContains('tenders_no_applications', $codes);
        $this->assertContains('leave_overlap', $codes);
    }

    public function test_insights_reports_graph_orphans_and_empty_objects(): void
    {
        $tenant = Tenant::create(['name' => 'Verwaist GmbH']);
        $user = User::factory()->create();
        tenancy()->initialize($tenant);
        $user->assignRole('holding');
        Sanctum::actingAs($user->fresh());
        tenancy()->end();

        $this->postJson('/api/v1/graph-entities', ['type' => 'company', 'name' => 'Waise AG'], ['X-Tenant' => $tenant->id])->assertCreated();
        $this->post('/api/v1/data-objects', ['name' => 'leer.txt', 'file' => UploadedFile::fake()->create('leer.txt', 0)], ['X-Tenant' => $tenant->id])->assertCreated();
        tenancy()->initialize($tenant);
        AiAnalysis::create(['kind' => 'insight', 'provider' => 'heuristic', 'status' => 'failed', 'summary' => 'Fehler']);
        tenancy()->end();

        $codes = collect($this->getJson('/api/v1/insights', ['X-Tenant' => $tenant->id])->json())
            ->pluck('code')->all();

        $this->assertContains('graph_orphans', $codes);
        $this->assertContains('data_objects_empty', $codes);
        $this->assertContains('ai_analyses_failed', $codes);
    }

    public function test_insights_reports_due_soon_reviews_strategies_audits_findings(): void
    {
        $tenant = Tenant::create(['name' => 'Fristen GmbH']);
        $user = User::factory()->create();
        tenancy()->initialize($tenant);
        $user->assignRole('holding');
        Sanctum::actingAs($user->fresh());
        tenancy()->end();

        $this->postJson('/api/v1/risk-assessments', ['title' => 'GB Lackier', 'review_at' => now()->addDays(3)], ['X-Tenant' => $tenant->id])->assertCreated();
        $this->postJson('/api/v1/strategies', ['name' => 'Wachstum', 'status' => 'active', 'ends_at' => now()->addDays(5)], ['X-Tenant' => $tenant->id])->assertCreated();
        $audit = $this->postJson('/api/v1/audits', ['title' => 'ISO-Audit', 'starts_on' => now()->addDays(5)->toDateString()], ['X-Tenant' => $tenant->id])->assertCreated()->json();
        $this->postJson('/api/v1/audit-findings', ['audit_id' => $audit['id'], 'title' => 'Kappe fehlt', 'due_at' => now()->addDays(4)], ['X-Tenant' => $tenant->id])->assertCreated();

        $codes = collect($this->getJson('/api/v1/insights', ['X-Tenant' => $tenant->id])->json())
            ->pluck('code')->all();

        $this->assertContains('risk_reviews_due_soon', $codes);
        $this->assertContains('strategies_ending_soon', $codes);
        $this->assertContains('audits_starting_soon', $codes);
        $this->assertContains('audit_findings_due_soon', $codes);
    }

    public function test_insights_reports_renewals_stalled_projects_and_old_instructions(): void
    {
        $tenant = Tenant::create(['name' => 'Altlasten GmbH']);
        $user = User::factory()->create();
        tenancy()->initialize($tenant);
        $user->assignRole('holding');
        Sanctum::actingAs($user->fresh());
        tenancy()->end();

        $this->postJson('/api/v1/tenders', ['title' => 'Ohne Budget'], ['X-Tenant' => $tenant->id])->assertCreated();
        $this->postJson('/api/v1/operating-instructions', ['title' => 'Galvanik BA', 'status' => 'active', 'valid_from' => now()->subYears(2)->toDateString()], ['X-Tenant' => $tenant->id])->assertCreated();

        tenancy()->initialize($tenant);
        Instruction::create(['title' => 'Alt-Unterweisung', 'status' => 'completed', 'interval_months' => 12, 'completed_at' => now()->subMonths(13)]);
        $stalled = Project::create(['name' => 'Liegengeblieben', 'status' => 'active', 'progress' => 0]);
        $stalled->created_at = now()->subDays(40);
        $stalled->save();
        tenancy()->end();

        $codes = collect($this->getJson('/api/v1/insights', ['X-Tenant' => $tenant->id])->json())
            ->pluck('code')->all();

        $this->assertContains('tenders_no_budget', $codes);
        $this->assertContains('op_instructions_review', $codes);
        $this->assertContains('instructions_renewal_due', $codes);
        $this->assertContains('projects_stalled', $codes);
    }

    public function test_insights_reports_measures_overdue_orders_soon_and_projects_ending(): void
    {
        $tenant = Tenant::create(['name' => 'Endspurt GmbH']);
        $user = User::factory()->create();
        tenancy()->initialize($tenant);
        $user->assignRole('holding');
        Sanctum::actingAs($user->fresh());
        tenancy()->end();

        $project = $this->postJson('/api/v1/projects', ['name' => 'Endet bald', 'status' => 'active', 'ends_at' => now()->addDays(5)], ['X-Tenant' => $tenant->id])->assertCreated()->json();
        $this->postJson('/api/v1/measures', ['title' => 'Überfällig', 'project_id' => $project['id'], 'due_at' => now()->subDays(2)], ['X-Tenant' => $tenant->id])->assertCreated();
        $this->postJson('/api/v1/production-orders', ['order_no' => 'PO-SOON', 'product' => 'Inlay', 'status' => 'queued', 'due_at' => now()->addDays(4)], ['X-Tenant' => $tenant->id])->assertCreated();

        $codes = collect($this->getJson('/api/v1/insights', ['X-Tenant' => $tenant->id])->json())
            ->pluck('code')->all();

        $this->assertContains('measures_overdue', $codes);
        $this->assertContains('orders_due_soon', $codes);
        $this->assertContains('projects_ending_soon', $codes);
        $this->assertContains('orders_no_machine', $codes);
    }

    public function test_insights_reports_missing_docs_and_persons(): void
    {
        $tenant = Tenant::create(['name' => 'Leerstand GmbH']);
        $user = User::factory()->create();
        tenancy()->initialize($tenant);
        $user->assignRole('holding');
        Sanctum::actingAs($user->fresh());
        tenancy()->end();

        $this->postJson('/api/v1/instructions', ['title' => 'Ohne Dokument und Person'], ['X-Tenant' => $tenant->id])->assertCreated();
        $this->postJson('/api/v1/inspections', ['title' => 'Ohne Person'], ['X-Tenant' => $tenant->id])->assertCreated();
        $done = $this->postJson('/api/v1/inspections', ['title' => 'Ohne Ergebnis'], ['X-Tenant' => $tenant->id])->assertCreated()->json();
        $this->putJson("/api/v1/inspections/{$done['id']}", ['status' => 'completed'], ['X-Tenant' => $tenant->id])->assertOk();
        $this->postJson('/api/v1/operating-instructions', ['title' => 'Entwurf ohne Dokument'], ['X-Tenant' => $tenant->id])->assertCreated();

        $codes = collect($this->getJson('/api/v1/insights', ['X-Tenant' => $tenant->id])->json())
            ->pluck('code')->all();

        $this->assertContains('instructions_no_document', $codes);
        $this->assertContains('instructions_no_person', $codes);
        $this->assertContains('inspections_no_person', $codes);
        $this->assertContains('inspections_no_result', $codes);
        $this->assertContains('op_instructions_draft', $codes);
        $this->assertContains('op_instructions_no_document', $codes);
    }

    public function test_insights_reports_missing_relations(): void
    {
        $tenant = Tenant::create(['name' => 'Waisenhaus GmbH']);
        $user = User::factory()->create();
        tenancy()->initialize($tenant);
        $user->assignRole('holding');
        Sanctum::actingAs($user->fresh());
        tenancy()->end();

        $this->postJson('/api/v1/companies', ['name' => 'Solo AG'], ['X-Tenant' => $tenant->id])->assertCreated();
        $this->postJson('/api/v1/persons', ['first_name' => 'Max', 'last_name' => 'Frei'], ['X-Tenant' => $tenant->id])->assertCreated();
        $this->postJson('/api/v1/strategies', ['name' => 'Ohne Projekte', 'status' => 'active'], ['X-Tenant' => $tenant->id])->assertCreated();
        $this->postJson('/api/v1/projects', ['name' => 'Ohne Strategie'], ['X-Tenant' => $tenant->id])->assertCreated();
        $this->postJson('/api/v1/portfolios', ['name' => 'Leer'], ['X-Tenant' => $tenant->id])->assertCreated();
        $this->postJson('/api/v1/deadlines', ['title' => 'Ohne Datensatz', 'due_at' => now()->addMonth()], ['X-Tenant' => $tenant->id])->assertCreated();

        tenancy()->initialize($tenant);
        Document::create(['title' => 'Ohne Version']);
        tenancy()->end();

        $codes = collect($this->getJson('/api/v1/insights', ['X-Tenant' => $tenant->id])->json())
            ->pluck('code')->all();

        $this->assertContains('companies_no_persons', $codes);
        $this->assertContains('persons_without_company', $codes);
        $this->assertContains('strategies_no_projects', $codes);
        $this->assertContains('projects_no_strategy', $codes);
        $this->assertContains('projects_no_measures', $codes);
        $this->assertContains('portfolios_no_investments', $codes);
        $this->assertContains('deadlines_no_subject', $codes);
        $this->assertContains('documents_no_version', $codes);
        $this->assertContains('documents_no_category', $codes);
    }

    public function test_insights_reports_expert_and_tender_gaps(): void
    {
        $tenant = Tenant::create(['name' => 'Bewerbung GmbH']);
        $user = User::factory()->create();
        tenancy()->initialize($tenant);
        $user->assignRole('holding');
        Sanctum::actingAs($user->fresh());
        tenancy()->end();

        $person = $this->postJson('/api/v1/persons', ['first_name' => 'An', 'last_name' => 'Onym'], ['X-Tenant' => $tenant->id])->assertCreated()->json();
        $expert = $this->postJson('/api/v1/expert-profiles', ['person_id' => $person['id'], 'status' => 'active'], ['X-Tenant' => $tenant->id])->assertCreated()->json();
        $tender = $this->postJson('/api/v1/tenders', ['title' => 'Vergabe-Lücke'], ['X-Tenant' => $tenant->id])->assertCreated()->json();
        $app = $this->postJson("/api/v1/tenders/{$tender['id']}/applications", ['expert_profile_id' => $expert['id']], ['X-Tenant' => $tenant->id])->assertCreated()->json();
        tenancy()->initialize($tenant);
        TenderApplication::whereKey($app['id'])->update(['created_at' => now()->subDays(20)]);
        tenancy()->end();
        $tender2 = $this->postJson('/api/v1/tenders', ['title' => 'Direktvergabe'], ['X-Tenant' => $tenant->id])->assertCreated()->json();
        $this->putJson("/api/v1/tenders/{$tender2['id']}", ['status' => 'awarded'], ['X-Tenant' => $tenant->id])->assertOk();

        $codes = collect($this->getJson('/api/v1/insights', ['X-Tenant' => $tenant->id])->json())
            ->pluck('code')->all();

        $this->assertContains('persons_no_contact', $codes);
        $this->assertContains('expert_profiles_incomplete', $codes);
        $this->assertContains('expert_profiles_no_rate', $codes);
        $this->assertContains('applications_no_price', $codes);
        $this->assertContains('applications_no_proposal', $codes);
        $this->assertContains('applications_stale', $codes);
        $this->assertContains('tenders_awarded_no_winner', $codes);
    }

    public function test_insights_reports_leave_and_compliance_gaps(): void
    {
        $tenant = Tenant::create(['name' => 'Lücken GmbH']);
        $user = User::factory()->create();
        tenancy()->initialize($tenant);
        $user->assignRole('holding');
        Sanctum::actingAs($user->fresh());
        tenancy()->end();

        $person = $this->postJson('/api/v1/persons', ['first_name' => 'Urla', 'last_name' => 'Uber'], ['X-Tenant' => $tenant->id])->assertCreated()->json();
        $leave = $this->postJson('/api/v1/leave-requests', ['person_id' => $person['id'], 'type' => 'vacation', 'starts_on' => now()->addWeek()->toDateString(), 'ends_on' => now()->addDays(10)->toDateString()], ['X-Tenant' => $tenant->id])->assertCreated()->json();
        $this->postJson('/api/v1/risk-assessments', ['title' => 'GB ohne Person', 'risk_level' => 'low', 'review_at' => now()->addMonth()->toDateString()], ['X-Tenant' => $tenant->id])->assertCreated();
        $auditDone = $this->postJson('/api/v1/audits', ['title' => 'Leeres Audit', 'starts_on' => now()->subDays(3)->toDateString(), 'ends_on' => now()->subDay()->toDateString()], ['X-Tenant' => $tenant->id])->assertCreated()->json();
        $this->putJson("/api/v1/audits/{$auditDone['id']}", ['status' => 'done'], ['X-Tenant' => $tenant->id])->assertOk();
        $auditOpen = $this->postJson('/api/v1/audits', ['title' => 'Aktives Audit'], ['X-Tenant' => $tenant->id])->assertCreated()->json();
        $this->postJson('/api/v1/audit-findings', ['audit_id' => $auditOpen['id'], 'title' => 'Kritisch', 'severity' => 'critical', 'due_at' => now()->subDays(2)->toDateString()], ['X-Tenant' => $tenant->id])->assertCreated();
        $this->postJson('/api/v1/measures', ['title' => 'Losgelöst'], ['X-Tenant' => $tenant->id])->assertCreated();

        tenancy()->initialize($tenant);
        LeaveRequest::whereKey($leave['id'])->update(['created_at' => now()->subDays(10)]);
        LeaveRequest::create(['person_id' => $person['id'], 'type' => 'sick', 'starts_on' => now()->subDays(2)->toDateString(), 'ends_on' => now()->subDay()->toDateString(), 'status' => 'rejected']);
        Instruction::create(['title' => 'Ohne Stempel', 'status' => 'completed']);
        tenancy()->end();

        $codes = collect($this->getJson('/api/v1/insights', ['X-Tenant' => $tenant->id])->json())
            ->pluck('code')->all();

        $this->assertContains('leave_pending_stale', $codes);
        $this->assertContains('leave_decided_no_stamp', $codes);
        $this->assertContains('instructions_no_completed_at', $codes);
        $this->assertContains('measures_no_project', $codes);
        $this->assertContains('risk_assessments_no_person', $codes);
        $this->assertContains('audits_no_findings', $codes);
        $this->assertContains('audits_no_result', $codes);
        $this->assertContains('audit_findings_critical', $codes);
        $this->assertContains('audit_findings_overdue', $codes);
        $this->assertContains('audit_findings_unassigned', $codes);
    }

    public function test_insights_reports_production_and_compliance_state(): void
    {
        $tenant = Tenant::create(['name' => 'Produktion GmbH']);
        $user = User::factory()->create();
        tenancy()->initialize($tenant);
        $user->assignRole('holding');
        Sanctum::actingAs($user->fresh());
        tenancy()->end();

        $machine = $this->postJson('/api/v1/machines', ['name' => 'Fräse 1', 'status' => 'maintenance', 'capacity_units_per_day' => 10], ['X-Tenant' => $tenant->id])->assertCreated()->json();
        $this->postJson('/api/v1/production-orders', ['order_no' => 'A-001', 'product' => 'Krone', 'machine_id' => $machine['id']], ['X-Tenant' => $tenant->id])->assertCreated();
        $this->postJson('/api/v1/instructions', ['title' => 'U1'], ['X-Tenant' => $tenant->id])->assertCreated();
        $this->postJson('/api/v1/instructions', ['title' => 'U2'], ['X-Tenant' => $tenant->id])->assertCreated();
        $this->postJson('/api/v1/tenders', ['title' => 'Offene Ausschreibung'], ['X-Tenant' => $tenant->id])->assertCreated();

        tenancy()->initialize($tenant);
        Machine::create(['name' => 'Altmaschine', 'status' => 'active', 'capacity_units_per_day' => 0]);
        ProductionOrder::create(['order_no' => 'A-002', 'product' => 'Brücke', 'status' => 'running']);
        ProductionOrder::create(['order_no' => 'A-003', 'product' => 'Inlay', 'status' => 'done', 'scrap_qty' => 3]);
        Project::create(['name' => 'Fertig aber offen', 'status' => 'done', 'progress' => 40]);
        Task::create(['title' => 'Erledigt ohne Stempel', 'status' => 'done']);
        Deadline::create(['title' => 'Frist fertig', 'due_at' => now()->addWeek(), 'status' => 'completed']);
        tenancy()->end();

        $codes = collect($this->getJson('/api/v1/insights', ['X-Tenant' => $tenant->id])->json())
            ->pluck('code')->all();

        $this->assertContains('compliance_rate_low', $codes);
        $this->assertContains('orders_machine_maintenance', $codes);
        $this->assertContains('machines_zero_capacity', $codes);
        $this->assertContains('orders_running_no_start', $codes);
        $this->assertContains('orders_done_no_finish', $codes);
        $this->assertContains('orders_done_incomplete', $codes);
        $this->assertContains('projects_done_incomplete', $codes);
        $this->assertContains('tasks_done_no_stamp', $codes);
        $this->assertContains('deadlines_completed_no_stamp', $codes);
        $this->assertContains('tenders_open', $codes);
    }

    public function test_insights_reports_investment_question_and_lake_gaps(): void
    {
        $tenant = Tenant::create(['name' => 'Kapital GmbH']);
        $user = User::factory()->create();
        tenancy()->initialize($tenant);
        $user->assignRole('holding');
        Sanctum::actingAs($user->fresh());
        tenancy()->end();

        $portfolio = $this->postJson('/api/v1/portfolios', ['name' => 'Buch I'], ['X-Tenant' => $tenant->id])->assertCreated()->json();
        $this->postJson('/api/v1/investments', ['portfolio_id' => $portfolio['id'], 'name' => 'Ohne Wert', 'cost_basis' => 1000], ['X-Tenant' => $tenant->id])->assertCreated();
        $inv = $this->postJson('/api/v1/investments', ['portfolio_id' => $portfolio['id'], 'name' => 'Alt bewertet', 'cost_basis' => 1000, 'current_value' => 1200], ['X-Tenant' => $tenant->id])->assertCreated()->json();
        $part = $this->postJson('/api/v1/participations', ['name' => 'Exit AG', 'stake_pct' => 25], ['X-Tenant' => $tenant->id])->assertCreated()->json();
        $this->putJson("/api/v1/participations/{$part['id']}", ['status' => 'exited'], ['X-Tenant' => $tenant->id])->assertOk();
        $this->postJson('/api/v1/projects', ['name' => 'Herrenlos'], ['X-Tenant' => $tenant->id])->assertCreated();
        $q = $this->postJson('/api/v1/questions', ['title' => 'Offen alt'], ['X-Tenant' => $tenant->id])->assertCreated()->json();
        $q2 = $this->postJson('/api/v1/questions', ['title' => 'Beantwortet leer'], ['X-Tenant' => $tenant->id])->assertCreated()->json();
        $this->putJson("/api/v1/questions/{$q2['id']}", ['status' => 'answered'], ['X-Tenant' => $tenant->id])->assertOk();
        $q3 = $this->postJson('/api/v1/questions', ['title' => 'Nur Antwort'], ['X-Tenant' => $tenant->id])->assertCreated()->json();
        $this->putJson("/api/v1/questions/{$q3['id']}", ['status' => 'answered'], ['X-Tenant' => $tenant->id])->assertOk();
        $e1 = $this->postJson('/api/v1/graph-entities', ['type' => 'company', 'name' => 'A'], ['X-Tenant' => $tenant->id])->assertCreated()->json();
        $e2 = $this->postJson('/api/v1/graph-entities', ['type' => 'person', 'name' => 'B'], ['X-Tenant' => $tenant->id])->assertCreated()->json();
        $edge = $this->postJson('/api/v1/graph-edges', ['from_entity_id' => $e1['id'], 'to_entity_id' => $e2['id'], 'relation' => 'works_at'], ['X-Tenant' => $tenant->id])->assertCreated()->json();
        $obj = $this->post('/api/v1/data-objects', ['name' => 'Ohne Kategorie', 'file' => UploadedFile::fake()->create('d.txt', 2)], ['X-Tenant' => $tenant->id])->assertCreated()->json();

        tenancy()->initialize($tenant);
        Investment::whereKey($inv['id'])->update(['valued_at' => now()->subDays(100)]);
        Question::whereKey($q['id'])->update(['created_at' => now()->subDays(20)]);
        Answer::create(['question_id' => $q3['id'], 'body' => 'So geht es']);
        GraphEdge::whereKey($edge['id'])->update(['relation' => '']);
        DataObject::whereKey($obj['id'])->update(['category' => null]);
        tenancy()->end();

        $codes = collect($this->getJson('/api/v1/insights', ['X-Tenant' => $tenant->id])->json())
            ->pluck('code')->all();

        $this->assertContains('investments_no_value', $codes);
        $this->assertContains('investments_stale_value', $codes);
        $this->assertContains('participations_exited_with_stake', $codes);
        $this->assertContains('projects_no_owner', $codes);
        $this->assertContains('questions_stale', $codes);
        $this->assertContains('questions_no_answers', $codes);
        $this->assertContains('questions_no_accepted', $codes);
        $this->assertContains('data_objects_no_category', $codes);
        $this->assertContains('graph_edges_no_relation', $codes);
    }

    public function test_insights_reports_all_clear_on_empty_tenant(): void
    {
        $tenant = Tenant::create(['name' => 'Sauber GmbH']);
        $user = User::factory()->create(['last_login_at' => now()]);
        tenancy()->initialize($tenant);
        $user->assignRole('holding');
        Sanctum::actingAs($user->fresh());
        tenancy()->end();

        $codes = collect($this->getJson('/api/v1/insights', ['X-Tenant' => $tenant->id])->json())
            ->pluck('code')->all();

        $this->assertSame(['all_clear'], $codes);
    }

    public function test_insights_reports_members_never_logged_in(): void
    {
        $tenant = Tenant::create(['name' => 'Login GmbH']);
        $user = $this->acting($tenant);
        $member = User::factory()->create();
        $member->assignRole('mitarbeiter');
        tenancy()->end();

        $codes = collect($this->getJson('/api/v1/insights', ['X-Tenant' => $tenant->id])->json())
            ->pluck('code')->all();

        $this->assertContains('members_never_logged_in', $codes);

        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        $member->forceFill(['last_login_at' => now()])->saveQuietly();
        $codes = collect($this->getJson('/api/v1/insights', ['X-Tenant' => $tenant->id])->json())
            ->pluck('code')->all();
        $this->assertNotContains('members_never_logged_in', $codes);
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

    public function test_every_event_group_and_action_has_german_labels(): void
    {
        $workspace = file_get_contents(resource_path('views/workspace.blade.php'));

        preg_match('/eventGroup\(t\).*?\}\[g\]/s', $workspace, $groupMatch);
        preg_match('/eventLabel\(t\).*?\}\[a\]/s', $workspace, $labelMatch);
        $groups = $groupMatch[0] ?? '';
        $labels = $labelMatch[0] ?? '';
        $this->assertNotEmpty($groups, 'eventGroup-Map nicht gefunden');
        $this->assertNotEmpty($labels, 'eventLabel-Map nicht gefunden');

        $recorder = file_get_contents(base_path('Modules/DataPlatform/app/Support/ActivityRecorder.php'));
        preg_match('/WATCHED\s*=\s*\[(.*?)\];/s', $recorder, $watchedMatch);
        preg_match_all("/=>\s*'([a-z_]+)'/", $watchedMatch[1] ?? '', $prefixMatch);
        $expectedGroups = array_merge(array_unique($prefixMatch[1]), ['user', 'role', 'tenant']);

        foreach ($expectedGroups as $g) {
            $this->assertStringContainsString("{$g}: '", $groups, "eventGroup fehlt Label für {$g}");
        }

        foreach (['created', 'updated', 'deleted', 'completed', 'approved', 'awarded', 'uploaded', 'answered', 'added', 'roles_updated', 'removed', 'permissions_updated'] as $a) {
            $this->assertStringContainsString("{$a}: ", $labels, "eventLabel fehlt Label für {$a}");
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
        $bySection = collect($res)->mapToGroups(fn ($r) => [$r['section'] => $r['id']]);
        $this->assertNotContains($fremdId, $bySection->get('graph-entities', collect())->all());
        $this->assertNotContains($fremdObj, $bySection->get('data-objects', collect())->all());

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

    public function test_notifications_prune_removes_old_read(): void
    {
        $tenant = Tenant::create(['name' => 'P GmbH']);
        $user = $this->acting($tenant);

        $notif = new class extends Notification
        {
            public function via($n)
            {
                return ['database'];
            }

            public function toArray($n)
            {
                return ['title' => 'X', 'kind' => 'frist'];
            }
        };
        $user->notify($notif);
        $user->notify($notif);
        $user->notify($notif);

        $ids = $user->notifications()->pluck('id');
        // 1: gelesen + alt -> wird geloescht; 2: gelesen + neu -> bleibt; 3: ungelesen + alt -> bleibt
        DB::table('notifications')->where('id', $ids[0])->update(['read_at' => now(), 'created_at' => now()->subDays(100)]);
        DB::table('notifications')->where('id', $ids[1])->update(['read_at' => now()]);
        DB::table('notifications')->where('id', $ids[2])->update(['created_at' => now()->subDays(100)]);

        $this->artisan('notifications:prune')->assertSuccessful();

        $remaining = $user->fresh()->notifications()->pluck('id')->sort()->values()->all();
        $this->assertEquals(collect([$ids[1], $ids[2]])->sort()->values()->all(), $remaining);
        $this->assertNull(DB::table('notifications')->where('id', $ids[0])->first());
    }

    public function test_notification_can_be_deleted(): void
    {
        $tenant = Tenant::create(['name' => 'D GmbH']);
        $user = $this->acting($tenant);

        $notif = new class extends Notification
        {
            public function via($n)
            {
                return ['database'];
            }

            public function toArray($n)
            {
                return ['title' => 'X', 'kind' => 'frist'];
            }
        };
        $user->notify($notif);
        $other = User::factory()->create();
        $other->notify($notif);
        $otherId = $other->notifications()->first()->id;

        $id = $user->notifications()->first()->id;
        $this->deleteJson('/api/v1/notifications/'.$id, [], ['X-Tenant' => $tenant->id])->assertNoContent();
        $this->assertNull($user->fresh()->notifications()->first());

        $this->deleteJson('/api/v1/notifications/'.$otherId, [], ['X-Tenant' => $tenant->id])->assertNotFound();
        $this->assertEquals(1, $other->fresh()->notifications()->count());
    }

    public function test_notifications_read_all_and_unread_count(): void
    {
        $tenant = Tenant::create(['name' => 'N2 GmbH']);
        $user = $this->acting($tenant);

        $notif = new class extends Notification
        {
            public function via($n)
            {
                return ['database'];
            }

            public function toArray($n)
            {
                return ['title' => 'X', 'kind' => 'frist'];
            }
        };
        $user->notify($notif);
        $user->notify($notif);
        $other = User::factory()->create();
        $other->notify($notif);

        $this->getJson('/api/v1/notifications/unread-count', ['X-Tenant' => $tenant->id])
            ->assertOk()->assertJson(['count' => 2]);

        $this->postJson('/api/v1/notifications/read-all', [], ['X-Tenant' => $tenant->id])->assertOk();

        $this->getJson('/api/v1/notifications/unread-count', ['X-Tenant' => $tenant->id])
            ->assertOk()->assertJson(['count' => 0]);
        $this->assertEquals(1, $other->fresh()->unreadNotifications()->count());
    }
}
