<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Modules\Compliance\Models\Deadline;
use Modules\Compliance\Models\RiskAssessment;
use Modules\Compliance\Notifications\ComplianceDueSoon;
use Modules\Core\Notifications\Assigned;
use Modules\CorporateDev\Models\Project;
use Tests\TestCase;

class ComplianceTest extends TestCase
{
    use RefreshDatabase;

    protected function actingWithTenant(Tenant $tenant): User
    {
        $user = User::factory()->create();
        tenancy()->initialize($tenant);
        $user->assignRole('holding');
        Sanctum::actingAs($user->fresh());

        return $user;
    }

    public function test_instruction_and_inspection_crud(): void
    {
        $tenant = Tenant::create(['name' => 'Comp GmbH']);
        $user = $this->actingWithTenant($tenant);

        $this->postJson('/api/v1/instructions', [
            'title' => 'Hygieneunterweisung',
            'responsible_id' => $user->id,
            'due_at' => now()->addDay()->toISOString(),
        ], ['X-Tenant' => $tenant->id])->assertCreated();

        $this->postJson('/api/v1/inspections', [
            'title' => 'DGUV V3 Prüfung',
            'scheduled_at' => now()->addDays(3)->toISOString(),
        ], ['X-Tenant' => $tenant->id])->assertCreated();

        $this->getJson('/api/v1/instructions', ['X-Tenant' => $tenant->id])
            ->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/inspections', ['X-Tenant' => $tenant->id])
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_compliance_is_tenant_scoped(): void
    {
        $tenantA = Tenant::create(['name' => 'A GmbH']);
        $tenantB = Tenant::create(['name' => 'B GmbH']);
        $user = $this->actingWithTenant($tenantA);

        $this->postJson('/api/v1/risk-assessments', [
            'title' => 'GB Labor',
            'risk_level' => 'high',
        ], ['X-Tenant' => $tenantA->id])->assertCreated();

        // Rolle ist pro Tenant: ohne Rolle auf B ist der Zugriff direkt verboten.
        // Fresh user instance: loaded role relations must not leak across tenants.
        Sanctum::actingAs(User::find($user->id));
        $this->getJson('/api/v1/risk-assessments', ['X-Tenant' => $tenantB->id])
            ->assertForbidden();
    }

    public function test_deadline_reminder_notifies_responsible_user(): void
    {
        Notification::fake();
        $tenant = Tenant::create(['name' => 'Frist GmbH']);
        $user = $this->actingWithTenant($tenant);

        $this->postJson('/api/v1/deadlines', [
            'title' => 'BGM Bericht',
            'responsible_id' => $user->id,
            'due_at' => now()->addHours(12)->toISOString(),
        ], ['X-Tenant' => $tenant->id])->assertCreated();

        tenancy()->initialize($tenant);
        Artisan::call('compliance:remind');

        Notification::assertSentTo($user, ComplianceDueSoon::class);
        $this->assertNotNull(Deadline::first()->reminded_at);
    }

    public function test_risk_review_reminder_notifies_assessor_user(): void
    {
        Notification::fake();
        $tenant = Tenant::create(['name' => 'GB GmbH']);
        $user = $this->actingWithTenant($tenant);

        $this->postJson('/api/v1/persons', [
            'first_name' => 'Max',
            'last_name' => 'Muster',
            'email' => $user->email,
        ], ['X-Tenant' => $tenant->id])->assertCreated();
        $personId = $this->getJson('/api/v1/persons', ['X-Tenant' => $tenant->id])->json('data.0.id');

        $this->postJson('/api/v1/risk-assessments', [
            'title' => 'GB Schreinerei',
            'person_id' => $personId,
            'review_at' => now()->addHours(12)->toISOString(),
        ], ['X-Tenant' => $tenant->id])->assertCreated();

        tenancy()->initialize($tenant);
        Artisan::call('compliance:remind');

        Notification::assertSentTo($user, function (ComplianceDueSoon $n) {
            return $n->kind === 'gefaehrdungsbeurteilung';
        });
        $this->assertNotNull(RiskAssessment::first()->reminded_at);
    }

    public function test_instruction_reminder_falls_back_to_person_email(): void
    {
        Notification::fake();
        $tenant = Tenant::create(['name' => 'UW GmbH']);
        $user = $this->actingWithTenant($tenant);

        $this->postJson('/api/v1/persons', [
            'first_name' => 'Erika',
            'last_name' => 'Muster',
            'email' => $user->email,
        ], ['X-Tenant' => $tenant->id])->assertCreated();
        $personId = $this->getJson('/api/v1/persons', ['X-Tenant' => $tenant->id])->json('data.0.id');

        $this->postJson('/api/v1/instructions', [
            'title' => 'Brandschutz',
            'person_id' => $personId,
            'due_at' => now()->addHours(12)->toISOString(),
        ], ['X-Tenant' => $tenant->id])->assertCreated();

        tenancy()->initialize($tenant);
        Artisan::call('compliance:remind');

        Notification::assertSentTo($user, function (ComplianceDueSoon $n) {
            return $n->kind === 'unterweisung';
        });
    }

    public function test_inspection_reminder_falls_back_to_person_email(): void
    {
        Notification::fake();
        $tenant = Tenant::create(['name' => 'Prüf GmbH']);
        $user = $this->actingWithTenant($tenant);

        $this->postJson('/api/v1/persons', [
            'first_name' => 'Karl',
            'last_name' => 'Prüfer',
            'email' => $user->email,
        ], ['X-Tenant' => $tenant->id])->assertCreated();
        $personId = $this->getJson('/api/v1/persons', ['X-Tenant' => $tenant->id])->json('data.0.id');

        $this->postJson('/api/v1/inspections', [
            'title' => 'Druckbehälterprüfung',
            'person_id' => $personId,
            'scheduled_at' => now()->addHours(12)->toISOString(),
        ], ['X-Tenant' => $tenant->id])->assertCreated();

        tenancy()->initialize($tenant);
        Artisan::call('compliance:remind');

        Notification::assertSentTo($user, function (ComplianceDueSoon $n) {
            return $n->kind === 'pruefung';
        });
    }

    public function test_deadline_assignment_notifies_responsible(): void
    {
        Notification::fake();
        $tenant = Tenant::create(['name' => 'FristAssign GmbH']);
        $this->actingWithTenant($tenant);
        $responsible = User::factory()->create();

        $this->postJson('/api/v1/deadlines', [
            'title' => 'Frist B',
            'responsible_id' => $responsible->id,
            'due_at' => now()->addDay()->toISOString(),
        ], ['X-Tenant' => $tenant->id])->assertCreated();

        Notification::assertSentTo($responsible, Assigned::class);
    }

    public function test_instruction_creation_notifies_linked_person_user(): void
    {
        Notification::fake();
        $tenant = Tenant::create(['name' => 'UWLinked GmbH']);
        $this->actingWithTenant($tenant);
        $instructed = User::factory()->create();

        $this->postJson('/api/v1/persons', [
            'first_name' => 'Erika',
            'last_name' => 'Muster',
            'email' => $instructed->email,
        ], ['X-Tenant' => $tenant->id])->assertCreated();
        $personId = $this->getJson('/api/v1/persons', ['X-Tenant' => $tenant->id])->json('data.0.id');

        $this->postJson('/api/v1/instructions', [
            'title' => 'Brandschutz',
            'person_id' => $personId,
            'due_at' => now()->addDay()->toISOString(),
        ], ['X-Tenant' => $tenant->id])->assertCreated();

        Notification::assertSentTo($instructed, function (Assigned $n) {
            return $n->kind === 'unterweisung';
        });
    }

    public function test_instruction_completion_notifies_linked_person_user(): void
    {
        Notification::fake();
        $tenant = Tenant::create(['name' => 'UWDone GmbH']);
        $this->actingWithTenant($tenant);
        $instructed = User::factory()->create();

        $this->postJson('/api/v1/persons', [
            'first_name' => 'Erika',
            'last_name' => 'Fertig',
            'email' => $instructed->email,
        ], ['X-Tenant' => $tenant->id])->assertCreated();
        $personId = $this->getJson('/api/v1/persons', ['X-Tenant' => $tenant->id])->json('data.0.id');

        $this->postJson('/api/v1/instructions', [
            'title' => 'Ersthelfer',
            'person_id' => $personId,
            'due_at' => now()->addDay()->toISOString(),
        ], ['X-Tenant' => $tenant->id])->assertCreated();
        $id = $this->getJson('/api/v1/instructions', ['X-Tenant' => $tenant->id])->json('data.0.id');

        $this->putJson("/api/v1/instructions/{$id}", ['status' => 'completed'], ['X-Tenant' => $tenant->id])->assertOk();

        Notification::assertSentToTimes($instructed, Assigned::class, 2);
    }

    public function test_risk_assessment_creation_notifies_linked_assessor(): void
    {
        Notification::fake();
        $tenant = Tenant::create(['name' => 'GBLinked GmbH']);
        $this->actingWithTenant($tenant);
        $assessor = User::factory()->create();

        $this->postJson('/api/v1/persons', [
            'first_name' => 'Karl',
            'last_name' => 'Beurteiler',
            'email' => $assessor->email,
        ], ['X-Tenant' => $tenant->id])->assertCreated();
        $personId = $this->getJson('/api/v1/persons', ['X-Tenant' => $tenant->id])->json('data.0.id');

        $this->postJson('/api/v1/risk-assessments', [
            'title' => 'Arbeitsplatz GB',
            'person_id' => $personId,
            'risk_level' => 'medium',
        ], ['X-Tenant' => $tenant->id])->assertCreated();

        Notification::assertSentTo($assessor, function (Assigned $n) {
            return $n->kind === 'gefaehrdungsbeurteilung';
        });
    }

    public function test_deadline_reminder_notifies_responsible(): void
    {
        Notification::fake();
        $tenant = Tenant::create(['name' => 'Frist GmbH']);
        $user = $this->actingWithTenant($tenant);

        $this->postJson('/api/v1/deadlines', [
            'title' => 'IHK-Meldung',
            'responsible_id' => $user->id,
            'due_at' => now()->addHours(12)->toISOString(),
        ], ['X-Tenant' => $tenant->id])->assertCreated();

        tenancy()->initialize($tenant);
        Artisan::call('compliance:remind');

        Notification::assertSentTo($user, function (ComplianceDueSoon $n) {
            return $n->kind === 'frist';
        });
    }

    public function test_audit_reminder_notifies_responsible(): void
    {
        Notification::fake();
        $tenant = Tenant::create(['name' => 'Audit GmbH']);
        $user = $this->actingWithTenant($tenant);

        $this->postJson('/api/v1/audits', [
            'title' => 'Externes Audit QM',
            'responsible_id' => $user->id,
            'starts_on' => now()->addHours(12)->toDateString(),
            'status' => 'planned',
        ], ['X-Tenant' => $tenant->id])->assertCreated();

        tenancy()->initialize($tenant);
        Artisan::call('compliance:remind');

        Notification::assertSentTo($user, function (ComplianceDueSoon $n) {
            return $n->kind === 'audit';
        });
    }

    public function test_measure_reminder_notifies_responsible(): void
    {
        Notification::fake();
        $tenant = Tenant::create(['name' => 'Maßnahmen GmbH']);
        $user = $this->actingWithTenant($tenant);

        $this->postJson('/api/v1/projects', [
            'name' => 'Arbeitssicherheit',
            'status' => 'active',
        ], ['X-Tenant' => $tenant->id])->assertCreated();
        $projectId = $this->getJson('/api/v1/projects', ['X-Tenant' => $tenant->id])->json('data.0.id');

        $this->postJson('/api/v1/measures', [
            'title' => 'Sicherheitsbelehrung',
            'project_id' => $projectId,
            'responsible_id' => $user->id,
            'due_at' => now()->addHours(12)->toISOString(),
        ], ['X-Tenant' => $tenant->id])->assertCreated();

        tenancy()->initialize($tenant);
        Artisan::call('compliance:remind');

        Notification::assertSentTo($user, function (ComplianceDueSoon $n) {
            return $n->kind === 'massnahme';
        });
    }

    public function test_production_order_reminder_uses_assignee_email(): void
    {
        Notification::fake();
        $tenant = Tenant::create(['name' => 'Auftrag GmbH']);
        $user = $this->actingWithTenant($tenant);

        $this->postJson('/api/v1/persons', [
            'first_name' => 'Otto',
            'last_name' => 'Werker',
            'email' => $user->email,
        ], ['X-Tenant' => $tenant->id])->assertCreated();
        $personId = $this->getJson('/api/v1/persons', ['X-Tenant' => $tenant->id])->json('data.0.id');

        $this->postJson('/api/v1/production-orders', [
            'order_no' => 'PO-TEST-1',
            'product' => 'Kronen 3er',
            'assigned_to' => $personId,
            'due_at' => now()->addHours(12)->toISOString(),
            'status' => 'queued',
        ], ['X-Tenant' => $tenant->id])->assertCreated();

        tenancy()->initialize($tenant);
        Artisan::call('compliance:remind');

        Notification::assertSentTo($user, function (ComplianceDueSoon $n) {
            return $n->kind === 'auftrag';
        });
    }

    public function test_project_reminder_notifies_owner(): void
    {
        Notification::fake();
        $tenant = Tenant::create(['name' => 'Projekt GmbH']);
        $user = $this->actingWithTenant($tenant);

        $this->postJson('/api/v1/projects', [
            'name' => 'ERP-Einführung',
            'owner_id' => $user->id,
            'ends_at' => now()->addHours(12)->toDateString(),
            'status' => 'active',
        ], ['X-Tenant' => $tenant->id])->assertCreated();

        tenancy()->initialize($tenant);
        Artisan::call('compliance:remind');

        Notification::assertSentTo($user, function (ComplianceDueSoon $n) {
            return $n->kind === 'projekt';
        });
        $this->assertNotNull(Project::first()->reminded_at);
    }

    public function test_risk_review_reminder_uses_assessor_email(): void
    {
        Notification::fake();
        $tenant = Tenant::create(['name' => 'GB GmbH']);
        $user = $this->actingWithTenant($tenant);

        $this->postJson('/api/v1/persons', [
            'first_name' => 'Karl',
            'last_name' => 'Prüfer',
            'email' => $user->email,
        ], ['X-Tenant' => $tenant->id])->assertCreated();
        $personId = $this->getJson('/api/v1/persons', ['X-Tenant' => $tenant->id])->json('data.0.id');

        $this->postJson('/api/v1/risk-assessments', [
            'title' => 'GB Werkstatt',
            'person_id' => $personId,
            'review_at' => now()->addHours(12)->toISOString(),
            'status' => 'open',
        ], ['X-Tenant' => $tenant->id])->assertCreated();

        tenancy()->initialize($tenant);
        Artisan::call('compliance:remind');

        Notification::assertSentTo($user, function (ComplianceDueSoon $n) {
            return $n->kind === 'gefaehrdungsbeurteilung';
        });
    }

    public function test_compliance_routes_require_auth(): void
    {
        $tenant = Tenant::create(['name' => 'Auth2 GmbH']);

        $this->getJson('/api/v1/deadlines', ['X-Tenant' => $tenant->id])
            ->assertUnauthorized();
    }
}
