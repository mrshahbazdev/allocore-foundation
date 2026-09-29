<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\DataPlatform\Models\MetricSnapshot;
use Tests\TestCase;

class DerivedKpisTest extends TestCase
{
    use RefreshDatabase;

    private function auth(): Tenant
    {
        $tenant = Tenant::create(['name' => 'KPI Tenant']);
        $user = User::factory()->create();
        tenancy()->initialize($tenant);
        $user->assignRole('holding');
        Sanctum::actingAs($user->fresh());
        tenancy()->end();

        return $tenant;
    }

    private function snap(string $metric, float $value, string $on = '2026-09-21'): void
    {
        MetricSnapshot::create(['metric' => $metric, 'value' => $value, 'captured_on' => $on]);
    }

    public function test_derived_kpis_from_ext_metrics(): void
    {
        $tenant = $this->auth();

        tenancy()->initialize($tenant);
        $this->snap('ext_revenue', 10000);
        $this->snap('ext_costs', 6000);
        $this->snap('ext_cash_in', 9000);
        $this->snap('ext_cash_out', 7500);
        $this->snap('ext_costs_marketing', 2000);
        $this->snap('ext_new_customers', 4);
        $this->snap('ext_leads', 40);
        $this->snap('ext_mql', 12);
        $this->snap('ext_revenue_paid', 8400);

        $res = $this->getJson('/api/v1/kpis', ['X-Tenant' => $tenant->id])->assertOk()->json('kpis');
        $kpis = collect($res)->keyBy('key');

        $this->assertEquals(4000, $kpis['gross_profit']['value']);
        $this->assertEquals(40, $kpis['gross_margin']['value']);
        $this->assertEquals(1500, $kpis['net_cash_flow']['value']);
        $this->assertEquals(500, $kpis['cac']['value']);
        $this->assertEquals(2100, $kpis['arpc']['value']);
        $this->assertEquals(10, $kpis['lead_to_customer_rate']['value']);
        $this->assertEquals(30, $kpis['mql_rate']['value']);
        tenancy()->end();
    }

    public function test_missing_inputs_are_omitted_and_zero_division_is_null(): void
    {
        $tenant = $this->auth();

        tenancy()->initialize($tenant);
        $this->snap('ext_costs_marketing', 1000); // no customers → CAC null
        $this->snap('ext_leads', 0.0);

        $kpis = collect($this->getJson('/api/v1/kpis', ['X-Tenant' => $tenant->id])->assertOk()->json('kpis'))->keyBy('key');

        $this->assertArrayNotHasKey('gross_profit', $kpis);
        $this->assertArrayNotHasKey('net_cash_flow', $kpis);
        $this->assertNull($kpis['cac']['value']);
        $this->assertNull($kpis['lead_to_customer_rate']['value']);
        tenancy()->end();
    }

    public function test_lab_kpis_from_order_metrics(): void
    {
        $tenant = $this->auth();

        tenancy()->initialize($tenant);
        $this->snap('ext_orders_done', 20);
        $this->snap('ext_orders_on_time', 17);
        $this->snap('ext_lead_time_days', 110);
        $this->snap('ext_complaints', 2);

        $kpis = collect($this->getJson('/api/v1/kpis', ['X-Tenant' => $tenant->id])->assertOk()->json('kpis'))->keyBy('key');

        $this->assertEquals(85, $kpis['on_time_delivery_rate']['value']);
        $this->assertEquals(5.5, $kpis['avg_order_lead_time_days']['value']);
        $this->assertEquals(10, $kpis['order_complaint_rate']['value']);
        tenancy()->end();
    }

    public function test_fin_ebitda_margin_and_date_window(): void
    {
        $tenant = $this->auth();

        tenancy()->initialize($tenant);
        $this->snap('fin_revenue', 50000, '2026-09-21');
        $this->snap('fin_ebitda', 7500, '2026-09-21');
        $this->snap('fin_revenue', 99999, '2026-09-25'); // newer, outside window

        $kpis = collect($this->getJson('/api/v1/kpis?date=2026-09-22', ['X-Tenant' => $tenant->id])->assertOk()->json('kpis'))->keyBy('key');
        $this->assertEquals(15, $kpis['ebitda_margin']['value']);

        $kpisAll = collect($this->getJson('/api/v1/kpis', ['X-Tenant' => $tenant->id])->assertOk()->json('kpis'))->keyBy('key');
        $this->assertEqualsWithDelta(7.5008, $kpisAll['ebitda_margin']['value'], 0.001);
        tenancy()->end();
    }
}
