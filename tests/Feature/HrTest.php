<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Notifications\Assigned;
use Modules\Hr\Notifications\LeaveDecided;
use Tests\TestCase;

class HrTest extends TestCase
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

    public function test_leave_request_lifecycle_and_scoping(): void
    {
        $tenantA = Tenant::create(['name' => 'A GmbH']);
        $tenantB = Tenant::create(['name' => 'B GmbH']);
        $user = $this->acting($tenantA);

        $person = $this->postJson('/api/v1/persons', [
            'first_name' => 'Max', 'last_name' => 'Tech',
        ], ['X-Tenant' => $tenantA->id]);
        $person->assertCreated();
        $pid = $person->json('id');

        $leave = $this->postJson('/api/v1/leave-requests', [
            'person_id' => $pid,
            'type' => 'vacation',
            'starts_on' => today()->toDateString(),
            'ends_on' => today()->addDays(10)->toDateString(),
        ], ['X-Tenant' => $tenantA->id])->assertCreated()->json();
        $this->assertEquals('pending', $leave['status']);

        $updated = $this->putJson("/api/v1/leave-requests/{$leave['id']}", [
            'status' => 'approved',
        ], ['X-Tenant' => $tenantA->id])->assertOk()->json();
        $this->assertEquals('approved', $updated['status']);
        $this->assertNotNull($updated['decided_at']);

        $this->getJson('/api/v1/leave-requests?active=1', ['X-Tenant' => $tenantA->id])
            ->assertOk()->assertJsonCount(1, 'data');

        Sanctum::actingAs(User::find($user->id));
        $this->getJson('/api/v1/leave-requests', ['X-Tenant' => $tenantB->id])->assertForbidden();
    }

    public function test_new_leave_request_notifies_hr_managers(): void
    {
        Notification::fake();
        $tenant = Tenant::create(['name' => 'Mgr GmbH']);
        $requester = $this->acting($tenant);

        $manager = User::factory()->create();
        tenancy()->initialize($tenant);
        $manager->assignRole('holding');
        tenancy()->end();

        $pid = $this->postJson('/api/v1/persons', [
            'first_name' => 'Max', 'last_name' => 'Tech',
        ], ['X-Tenant' => $tenant->id])->assertCreated()->json('id');

        $this->postJson('/api/v1/leave-requests', [
            'person_id' => $pid, 'type' => 'vacation',
            'starts_on' => today()->toDateString(), 'ends_on' => today()->addDays(5)->toDateString(),
        ], ['X-Tenant' => $tenant->id])->assertCreated();

        Notification::assertSentTo($manager, Assigned::class,
            fn ($n) => $n->kind === 'urlaub' && str_contains($n->title, 'Max Tech'));
        Notification::assertNotSentTo($requester, Assigned::class,
            fn ($n) => $n->kind === 'urlaub');
    }

    public function test_leave_decision_notifies_requester(): void
    {
        Notification::fake();
        $tenant = Tenant::create(['name' => 'HR GmbH']);
        $this->acting($tenant);
        $member = User::factory()->create();

        $pid = $this->postJson('/api/v1/persons', [
            'first_name' => 'Max', 'last_name' => 'Tech', 'email' => $member->email,
        ], ['X-Tenant' => $tenant->id])->assertCreated()->json('id');

        $leave = $this->postJson('/api/v1/leave-requests', [
            'person_id' => $pid, 'type' => 'vacation',
            'starts_on' => today()->toDateString(), 'ends_on' => today()->addDays(5)->toDateString(),
        ], ['X-Tenant' => $tenant->id])->assertCreated()->json();

        $this->putJson("/api/v1/leave-requests/{$leave['id']}", [
            'status' => 'approved',
        ], ['X-Tenant' => $tenant->id])->assertOk();

        Notification::assertSentTo($member, LeaveDecided::class);
    }
}
