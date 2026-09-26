<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Models\Company;
use Modules\Core\Notifications\Assigned;
use Modules\Tasks\Models\Task;
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

    public function test_tasks_and_masterdata_filters(): void
    {
        $tenant = Tenant::create(['name' => 'Filter GmbH']);
        $user = $this->actingWithTenant($tenant);

        $this->postJson('/api/v1/tasks', [
            'title' => 'Überfällig', 'assignee_id' => $user->id, 'due_at' => now()->subDay()->toISOString(),
        ], ['X-Tenant' => $tenant->id]);
        $this->postJson('/api/v1/tasks', [
            'title' => 'Offen ohne zugewiesenen', 'due_at' => now()->addWeek()->toISOString(),
        ], ['X-Tenant' => $tenant->id]);

        $this->getJson('/api/v1/tasks?overdue=1', ['X-Tenant' => $tenant->id])
            ->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/tasks?unassigned=1', ['X-Tenant' => $tenant->id])
            ->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/tasks?assignee_id='.$user->id, ['X-Tenant' => $tenant->id])
            ->assertOk()->assertJsonCount(1, 'data');

        $company = $this->postJson('/api/v1/companies', ['name' => 'Musterfirma'], ['X-Tenant' => $tenant->id]);
        $this->postJson('/api/v1/companies', ['name' => 'Andere GmbH'], ['X-Tenant' => $tenant->id]);
        $this->postJson('/api/v1/persons', [
            'first_name' => 'Max', 'last_name' => 'Mustermann', 'company_id' => $company->json('id'),
        ], ['X-Tenant' => $tenant->id]);

        $this->getJson('/api/v1/companies?q=Muster', ['X-Tenant' => $tenant->id])
            ->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/persons?q=mustermann', ['X-Tenant' => $tenant->id])
            ->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/persons?company_id='.$company->json('id'), ['X-Tenant' => $tenant->id])
            ->assertOk()->assertJsonCount(1, 'data');
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

        Notification::assertSentTo($assignee, Assigned::class);

        $other = User::factory()->create();
        $this->putJson('/api/v1/tasks/'.Task::first()->id, [
            'assignee_id' => $other->id,
        ], ['X-Tenant' => $tenant->id])->assertOk();

        Notification::assertSentTo($other, Assigned::class);
    }

    public function test_task_done_notifies_creator(): void
    {
        Notification::fake();
        $tenant = Tenant::create(['name' => 'Done GmbH']);
        $creator = $this->actingWithTenant($tenant);
        $assignee = User::factory()->create();

        $this->postJson('/api/v1/tasks', [
            'title' => 'Bericht prüfen',
            'assignee_id' => $assignee->id,
        ], ['X-Tenant' => $tenant->id])->assertCreated();

        tenancy()->initialize($tenant);
        $assignee->assignRole('holding');
        tenancy()->end();
        Sanctum::actingAs($assignee->fresh());

        $this->putJson('/api/v1/tasks/'.Task::first()->id, [
            'status' => 'done',
        ], ['X-Tenant' => $tenant->id])->assertOk();

        Notification::assertSentTo($creator, Assigned::class,
            fn ($n) => $n->kind === 'aufgabe' && str_contains($n->title, 'erledigt'));
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

    public function test_me_password_update_revokes_tokens(): void
    {
        $tenant = Tenant::create(['name' => 'PW GmbH']);
        $user = $this->actingWithTenant($tenant);
        $user->update(['password' => bcrypt('Altes-Passwort-1')]);
        Sanctum::actingAs($user->fresh());
        $user->createToken('api')->plainTextToken;
        $this->assertSame(1, $user->tokens()->count());

        $this->putJson('/api/v1/me/password', [
            'current_password' => 'Altes-Passwort-1',
            'password' => 'Neues-Passwort-1',
            'password_confirmation' => 'Neues-Passwort-1',
        ], ['X-Tenant' => $tenant->id])->assertOk();

        $this->assertSame(0, $user->tokens()->count());
        $this->assertTrue(Hash::check('Neues-Passwort-1', $user->fresh()->password));
    }

    public function test_me_password_update_rejects_wrong_current(): void
    {
        $tenant = Tenant::create(['name' => 'PW2 GmbH']);
        $user = $this->actingWithTenant($tenant);
        $user->update(['password' => bcrypt('Altes-Passwort-1')]);

        $this->putJson('/api/v1/me/password', [
            'current_password' => 'falsch',
            'password' => 'Neues-Passwort-1',
            'password_confirmation' => 'Neues-Passwort-1',
        ], ['X-Tenant' => $tenant->id])->assertUnprocessable();
    }

    public function test_me_update_changes_name_and_email(): void
    {
        $tenant = Tenant::create(['name' => 'Me GmbH']);
        $user = $this->actingWithTenant($tenant);

        $this->putJson('/api/v1/me', [
            'name' => 'Neuer Name',
            'email' => 'neu@example.de',
        ], ['X-Tenant' => $tenant->id])
            ->assertOk()
            ->assertJsonPath('name', 'Neuer Name')
            ->assertJsonPath('email', 'neu@example.de');

        $this->assertSame('Neuer Name', $user->fresh()->name);
    }

    public function test_me_update_rejects_taken_email(): void
    {
        $tenant = Tenant::create(['name' => 'Me2 GmbH']);
        $this->actingWithTenant($tenant);
        User::factory()->create(['email' => 'vergeben@example.de']);

        $this->putJson('/api/v1/me', ['email' => 'vergeben@example.de'], ['X-Tenant' => $tenant->id])
            ->assertUnprocessable();
    }

    public function test_tenant_routes_require_auth(): void
    {
        $tenant = Tenant::create(['name' => 'Auth GmbH']);

        $this->getJson('/api/v1/companies', ['X-Tenant' => $tenant->id])
            ->assertUnauthorized();
    }

    public function test_tenant_show_returns_current_tenant_with_member_count(): void
    {
        $tenant = Tenant::create(['name' => 'Info GmbH']);
        $this->actingWithTenant($tenant);

        $res = $this->getJson('/api/v1/tenant', ['X-Tenant' => $tenant->id])->assertOk();

        $this->assertSame((string) $tenant->id, $res->json('id'));
        $this->assertSame('Info GmbH', $res->json('name'));
        $this->assertSame(1, $res->json('members_count'));
    }
}
