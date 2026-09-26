<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Modules\Audits\Models\Audit;
use Modules\Audits\Models\AuditFinding;
use Modules\Compliance\Notifications\ComplianceDueSoon;
use Tests\TestCase;

class AuditsTest extends TestCase
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

    public function test_audit_crud_and_findings_scoped(): void
    {
        $tenantA = Tenant::create(['name' => 'A GmbH']);
        $tenantB = Tenant::create(['name' => 'B GmbH']);
        $user = $this->acting($tenantA);

        $audit = $this->postJson('/api/v1/audits', [
            'title' => 'Internes Audit Q4', 'type' => 'internal', 'standard' => 'ISO 9001',
        ], ['X-Tenant' => $tenantA->id])->assertCreated()->json();

        $finding = $this->postJson('/api/v1/audit-findings', [
            'audit_id' => $audit['id'], 'title' => 'Fehlende GB', 'severity' => 'high',
            'due_at' => now()->addDays(10)->toDateString(),
        ], ['X-Tenant' => $tenantA->id])->assertCreated()->json();

        $this->putJson('/api/v1/audit-findings/'.$finding['id'], ['status' => 'resolved'], ['X-Tenant' => $tenantA->id])
            ->assertOk()->assertJsonPath('status', 'resolved');

        $this->getJson('/api/v1/audits?status=planned', ['X-Tenant' => $tenantA->id])
            ->assertOk()->assertJsonCount(1, 'data');

        // audit-FK muss zum Tenant gehören: Audit aus B nicht referenzierbar
        $auditB = $this->postJson('/api/v1/audits', ['title' => 'B Audit'], ['X-Tenant' => $tenantB->id])->assertCreated()->json();
        Sanctum::actingAs(User::find($user->id));
        $this->postJson('/api/v1/audit-findings', ['audit_id' => $auditB['id'], 'title' => 'x'], ['X-Tenant' => $tenantA->id])
            ->assertStatus(404);
    }

    public function test_audits_require_permission(): void
    {
        $tenant = Tenant::create(['name' => 'C GmbH']);
        $user = User::factory()->create();
        tenancy()->initialize($tenant);
        $user->assignRole('kunde');
        Sanctum::actingAs($user->fresh());
        tenancy()->end();

        $this->postJson('/api/v1/audits', ['title' => 'X'], ['X-Tenant' => $tenant->id])->assertForbidden();
        $this->getJson('/api/v1/audits', ['X-Tenant' => $tenant->id])->assertForbidden();
    }

    public function test_auditor_role_can_manage_audits(): void
    {
        $tenant = Tenant::create(['name' => 'D GmbH']);
        $user = User::factory()->create();
        tenancy()->initialize($tenant);
        $user->assignRole('auditor');
        Sanctum::actingAs($user->fresh());
        tenancy()->end();

        $this->postJson('/api/v1/audits', ['title' => 'Externes Audit'], ['X-Tenant' => $tenant->id])->assertCreated();
    }

    public function test_finding_reminder_notifies_responsible(): void
    {
        Notification::fake();
        $tenant = Tenant::create(['name' => 'E GmbH']);
        $user = $this->acting($tenant);

        $audit = $this->postJson('/api/v1/audits', ['title' => 'ISO Audit'], ['X-Tenant' => $tenant->id])->assertCreated()->json();
        $this->postJson('/api/v1/audit-findings', [
            'audit_id' => $audit['id'], 'title' => 'Kritisch', 'severity' => 'high',
            'responsible_id' => $user->id, 'due_at' => now()->addHours(12)->toDateString(),
        ], ['X-Tenant' => $tenant->id])->assertCreated();

        tenancy()->initialize($tenant);
        Artisan::call('compliance:remind');

        Notification::assertSentTo($user, ComplianceDueSoon::class, fn ($n) => $n->kind === 'feststellung');
        $this->assertNotNull(AuditFinding::first()->reminded_at);
    }

    public function test_audit_start_reminder_notifies_responsible(): void
    {
        Notification::fake();
        $tenant = Tenant::create(['name' => 'F GmbH']);
        $user = $this->acting($tenant);

        $this->postJson('/api/v1/audits', [
            'title' => 'ISO Audit', 'starts_on' => now()->addHours(12)->toDateString(),
            'responsible_id' => $user->id,
        ], ['X-Tenant' => $tenant->id])->assertCreated();

        tenancy()->initialize($tenant);
        Artisan::call('compliance:remind');

        Notification::assertSentTo($user, ComplianceDueSoon::class, fn ($n) => $n->kind === 'audit');
        $this->assertNotNull(Audit::first()->reminded_at);
    }

    public function test_measure_reminder_notifies_responsible(): void
    {
        Notification::fake();
        $tenant = Tenant::create(['name' => 'G GmbH']);
        $user = $this->acting($tenant);

        $strategy = $this->postJson('/api/v1/strategies', ['name' => 'S1'], ['X-Tenant' => $tenant->id])->assertCreated()->json();
        $project = $this->postJson('/api/v1/projects', ['strategy_id' => $strategy['id'], 'name' => 'P1'], ['X-Tenant' => $tenant->id])->assertCreated()->json();
        $this->postJson('/api/v1/measures', [
            'project_id' => $project['id'], 'title' => 'M1',
            'responsible_id' => $user->id, 'due_at' => now()->addHours(12)->toDateString(),
        ], ['X-Tenant' => $tenant->id])->assertCreated();

        tenancy()->initialize($tenant);
        Artisan::call('compliance:remind');

        Notification::assertSentTo($user, ComplianceDueSoon::class, fn ($n) => $n->kind === 'massnahme');
    }

    public function test_tender_deadline_reminder_notifies_creator(): void
    {
        Notification::fake();
        $tenant = Tenant::create(['name' => 'H GmbH']);
        $user = $this->acting($tenant);

        $this->postJson('/api/v1/tenders', [
            'title' => 'Gebäudereinigung', 'deadline_at' => now()->addHours(12)->toISOString(),
        ], ['X-Tenant' => $tenant->id])->assertCreated();

        tenancy()->initialize($tenant);
        Artisan::call('compliance:remind');

        Notification::assertSentTo($user, ComplianceDueSoon::class, fn ($n) => $n->kind === 'ausschreibung');
    }
}
