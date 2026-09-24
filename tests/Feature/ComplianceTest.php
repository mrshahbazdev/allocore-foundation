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
use Modules\Compliance\Notifications\ComplianceDueSoon;
use Tests\TestCase;

class ComplianceTest extends TestCase
{
    use RefreshDatabase;

    protected function actingWithTenant(Tenant $tenant): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

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
        $this->actingWithTenant($tenantA);

        $this->postJson('/api/v1/risk-assessments', [
            'title' => 'GB Labor',
            'risk_level' => 'high',
        ], ['X-Tenant' => $tenantA->id])->assertCreated();

        $this->getJson('/api/v1/risk-assessments', ['X-Tenant' => $tenantB->id])
            ->assertOk()->assertJsonCount(0, 'data');
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

    public function test_compliance_routes_require_auth(): void
    {
        $tenant = Tenant::create(['name' => 'Auth2 GmbH']);

        $this->getJson('/api/v1/deadlines', ['X-Tenant' => $tenant->id])
            ->assertUnauthorized();
    }
}
