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

    public function test_compliance_routes_require_auth(): void
    {
        $tenant = Tenant::create(['name' => 'Auth2 GmbH']);

        $this->getJson('/api/v1/deadlines', ['X-Tenant' => $tenant->id])
            ->assertUnauthorized();
    }
}
