<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Models\Company;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Notifications\TaskAssigned;
use Modules\Tasks\Notifications\TaskDueSoon;
use Tests\TestCase;

class CorePlatformTest extends TestCase
{
    use RefreshDatabase;

    protected function actingWithTenant(Tenant $tenant, string $role = 'holding'): User
    {
        $user = User::factory()->create();
        tenancy()->initialize($tenant);
        $user->assignRole($role);
        Sanctum::actingAs($user->fresh());

        return $user;
    }

    public function test_company_crud_is_tenant_scoped(): void
    {
        $tenantA = Tenant::create(['name' => 'A GmbH']);
        $tenantB = Tenant::create(['name' => 'B GmbH']);
        $user = $this->actingWithTenant($tenantA);

        $this->postJson('/api/v1/companies', ['name' => 'DentalTech'], ['X-Tenant' => $tenantA->id])
            ->assertCreated();

        $this->getJson('/api/v1/companies', ['X-Tenant' => $tenantA->id])
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // Same user, other tenant: role is per-tenant -> forbidden
        // Fresh user instance: loaded role relations must not leak across tenants.
        Sanctum::actingAs(User::find($user->id));
        $this->getJson('/api/v1/companies', ['X-Tenant' => $tenantB->id])
            ->assertForbidden();
    }

    public function test_company_is_auto_stamped_with_tenant_id(): void
    {
        $tenant = Tenant::create(['name' => 'Stamp GmbH']);
        $this->actingWithTenant($tenant);

        $this->postJson('/api/v1/companies', ['name' => 'Auto'], ['X-Tenant' => $tenant->id])
            ->assertCreated();

        $this->assertSame($tenant->getTenantKey(), Company::withoutGlobalScopes()->first()->tenant_id);
    }

    public function test_task_create_and_list(): void
    {
        $tenant = Tenant::create(['name' => 'Tasks GmbH']);
        $user = $this->actingWithTenant($tenant);

        $this->postJson('/api/v1/tasks', [
            'title' => 'Unterweisung planen',
            'assignee_id' => $user->id,
            'due_at' => now()->addDay()->toISOString(),
        ], ['X-Tenant' => $tenant->id])->assertCreated();

        $this->getJson('/api/v1/tasks', ['X-Tenant' => $tenant->id])
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Unterweisung planen');
    }

    public function test_tasks_remind_notifies_assignee_and_stamps_reminded_at(): void
    {
        Notification::fake();
        $tenant = Tenant::create(['name' => 'Remind GmbH']);
        $user = $this->actingWithTenant($tenant);

        $this->postJson('/api/v1/tasks', [
            'title' => 'Protokoll abgeben',
            'assignee_id' => $user->id,
            'due_at' => now()->addHours(12)->toISOString(),
        ], ['X-Tenant' => $tenant->id])->assertCreated();

        tenancy()->initialize($tenant);
        Artisan::call('tasks:remind');

        Notification::assertSentTo($user, TaskDueSoon::class);
        $this->assertNotNull(Task::first()->reminded_at);
    }

    public function test_task_assignment_notifies_assignee(): void
    {
        Notification::fake();
        $tenant = Tenant::create(['name' => 'Assign GmbH']);
        $this->actingWithTenant($tenant);
        $assignee = User::factory()->create();

        $this->postJson('/api/v1/tasks', [
            'title' => 'Bericht prüfen',
            'assignee_id' => $assignee->id,
        ], ['X-Tenant' => $tenant->id])->assertCreated();

        Notification::assertSentTo($assignee, TaskAssigned::class);

        $other = User::factory()->create();
        $this->putJson('/api/v1/tasks/'.Task::first()->id, [
            'assignee_id' => $other->id,
        ], ['X-Tenant' => $tenant->id])->assertOk();

        Notification::assertSentTo($other, TaskAssigned::class);
    }

    public function test_tasks_remind_skips_already_reminded_tasks(): void
    {
        Notification::fake();
        $tenant = Tenant::create(['name' => 'Skip GmbH']);
        $user = $this->actingWithTenant($tenant);

        Task::create([
            'title' => 'Schon erinnert',
            'assignee_id' => $user->id,
            'due_at' => now()->addHours(12),
            'status' => Task::STATUS_OPEN,
            'reminded_at' => now(),
        ]);

        tenancy()->initialize($tenant);
        Artisan::call('tasks:remind');

        Notification::assertNothingSent();
    }

    public function test_list_endpoints_honor_per_page_param_and_cap(): void
    {
        $tenant = Tenant::create(['name' => 'Page GmbH']);
        $this->actingWithTenant($tenant);

        foreach (['C1', 'C2', 'C3'] as $name) {
            $this->postJson('/api/v1/companies', ['name' => $name], ['X-Tenant' => $tenant->id])->assertCreated();
        }

        $this->getJson('/api/v1/companies?per_page=2', ['X-Tenant' => $tenant->id])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('total', 3)
            ->assertJsonPath('per_page', 2);

        // per_page is capped at 200
        $this->getJson('/api/v1/companies?per_page=9999', ['X-Tenant' => $tenant->id])
            ->assertOk()
            ->assertJsonPath('per_page', 200);
    }

    public function test_tenant_routes_require_auth(): void
    {
        $tenant = Tenant::create(['name' => 'Auth GmbH']);

        $this->getJson('/api/v1/companies', ['X-Tenant' => $tenant->id])
            ->assertUnauthorized();
    }
}
