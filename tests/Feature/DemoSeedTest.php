<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Compliance\Models\Instruction;
use Modules\Core\Models\Company;
use Modules\Core\Models\Person;
use Modules\DataPlatform\Models\MetricSnapshot;
use Modules\Investments\Models\Portfolio;
use Modules\Production\Models\Machine;
use Modules\Tasks\Models\Task;
use Tests\TestCase;

class DemoSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seed_populates_all_domains_idempotently(): void
    {
        $tenant = Tenant::create(['name' => 'Demo GmbH']);
        User::factory()->create();

        $this->artisan('demo:seed', ['tenant' => $tenant->id])->assertSuccessful();

        tenancy()->initialize($tenant);

        $this->assertGreaterThanOrEqual(2, Company::count());
        $this->assertGreaterThanOrEqual(2, Person::count());
        $this->assertSame(2, Task::count());
        $this->assertSame(1, Instruction::count());
        $this->assertSame(1, Portfolio::count());
        $this->assertSame(1, Machine::count());
        $this->assertGreaterThanOrEqual(5, MetricSnapshot::count());

        $this->artisan('demo:seed', ['tenant' => $tenant->id])->assertSuccessful();
        $this->assertSame(2, Task::count(), 'second run must not duplicate rows');
    }

    public function test_workspace_page_renders_for_logged_in_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/app')->assertOk()->assertSee('ALLOCORE');
        $this->actingAs($user)->get('/app/companies')->assertOk();
    }
}
