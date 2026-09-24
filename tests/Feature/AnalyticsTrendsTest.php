<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\DataPlatform\Models\MetricSnapshot;
use Tests\TestCase;

class AnalyticsTrendsTest extends TestCase
{
    use RefreshDatabase;

    private function auth(string $role = 'holding'): array
    {
        $tenant = Tenant::create(['name' => 'Trend '.$role]);
        $user = User::factory()->create();
        tenancy()->initialize($tenant);
        $user->assignRole($role);
        Sanctum::actingAs($user->fresh());
        tenancy()->end();

        return [$tenant, $user];
    }

    public function test_trends_reports_direction_and_delta(): void
    {
        [$tenant] = $this->auth();

        tenancy()->initialize($tenant);
        MetricSnapshot::create(['metric' => 'companies', 'value' => 5, 'captured_on' => '2026-09-20']);
        MetricSnapshot::create(['metric' => 'companies', 'value' => 8, 'captured_on' => '2026-09-21']);
        MetricSnapshot::create(['metric' => 'tasks', 'value' => 3, 'captured_on' => '2026-09-21']);
        tenancy()->end();

        $res = $this->getJson('/api/v1/analytics/trends', ['X-Tenant' => $tenant->id]);
        $res->assertOk();

        $rows = collect($res->json())->keyBy('metric');
        $this->assertSame('up', $rows['companies']['direction']);
        $this->assertEquals(3.0, $rows['companies']['delta']);
        $this->assertSame('unknown', $rows['tasks']['direction']);
    }

    public function test_trends_requires_metrics_view(): void
    {
        [$tenant] = $this->auth('kunde');
        $this->getJson('/api/v1/analytics/trends', ['X-Tenant' => $tenant->id])->assertForbidden();
    }
}
