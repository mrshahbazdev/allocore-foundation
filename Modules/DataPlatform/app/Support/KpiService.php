<?php

namespace Modules\DataPlatform\Support;

use Modules\DataPlatform\Models\MetricSnapshot;

/**
 * Leitet abgeleitete KPIs aus atomaren ext_- und fin_-Snapshots ab.
 * Abgeleitete Kennzahlen werden read-seitig berechnet, nie persistiert —
 * Formeln bleiben so nachträglich anpassbar.
 */
class KpiService
{
    /**
     * @return array<int, array{key: string, value: float|null, unit: string, inputs: array<string, float>}>
     */
    public static function derived(string $tenantKey, ?string $date = null): array
    {
        $latest = MetricSnapshot::query()
            ->select('metric', 'value')
            ->where('tenant_id', $tenantKey)
            ->when($date, fn ($q) => $q->where('captured_on', '<=', $date))
            ->whereIn('id', fn ($q) => $q->selectRaw('MAX(id)')
                ->from('metric_snapshots')
                ->where('tenant_id', $tenantKey)
                ->when($date, fn ($q2) => $q2->where('captured_on', '<=', $date))
                ->groupBy('metric'))
            ->pluck('value', 'metric')
            ->map(fn ($v) => (float) $v)
            ->all();

        $kpi = function (string $key, string $unit, callable $fn) use ($latest) {
            try {
                $value = $fn($latest);
            } catch (\DivisionByZeroError) {
                $value = null;
            }

            return ['key' => $key, 'value' => $value !== null ? round((float) $value, 4) : null, 'unit' => $unit];
        };

        $v = fn (string $m) => $latest[$m] ?? 0.0;
        $has = fn (string ...$ms) => count(array_filter($ms, fn ($m) => isset($latest[$m]))) > 0;

        $kpis = [];

        if ($has('ext_revenue', 'ext_costs')) {
            $kpis[] = $kpi('gross_profit', 'eur', fn () => $v('ext_revenue') - $v('ext_costs'));
            $kpis[] = $kpi('gross_margin', 'pct', fn () => $v('ext_revenue') !== 0.0 ? ($v('ext_revenue') - $v('ext_costs')) / $v('ext_revenue') * 100 : throw new \DivisionByZeroError);
        }

        if ($has('ext_cash_in', 'ext_cash_out')) {
            $kpis[] = $kpi('net_cash_flow', 'eur', fn () => $v('ext_cash_in') - $v('ext_cash_out'));
        }

        if ($has('fin_ebitda', 'fin_revenue')) {
            $kpis[] = $kpi('ebitda_margin', 'pct', fn () => $v('fin_revenue') !== 0.0 ? $v('fin_ebitda') / $v('fin_revenue') * 100 : throw new \DivisionByZeroError);
        }

        if ($has('ext_costs_marketing', 'ext_new_customers')) {
            $kpis[] = $kpi('cac', 'eur', fn () => $v('ext_new_customers') !== 0.0 ? $v('ext_costs_marketing') / $v('ext_new_customers') : throw new \DivisionByZeroError);
        }

        if ($has('ext_revenue_paid', 'ext_new_customers')) {
            $kpis[] = $kpi('arpc', 'eur', fn () => $v('ext_new_customers') !== 0.0 ? $v('ext_revenue_paid') / $v('ext_new_customers') : throw new \DivisionByZeroError);
        }

        if ($has('ext_leads')) {
            $kpis[] = $kpi('lead_to_customer_rate', 'pct', fn () => $v('ext_leads') !== 0.0 ? $v('ext_new_customers') / $v('ext_leads') * 100 : throw new \DivisionByZeroError);
            $kpis[] = $kpi('mql_rate', 'pct', fn () => $v('ext_leads') !== 0.0 ? $v('ext_mql') / $v('ext_leads') * 100 : throw new \DivisionByZeroError);
        }

        if ($has('ext_web_visitors')) {
            $kpis[] = $kpi('visitor_to_lead_rate', 'pct', fn () => $v('ext_web_visitors') !== 0.0 ? $v('ext_leads') / $v('ext_web_visitors') * 100 : throw new \DivisionByZeroError);
        }

        if ($has('ext_billable_hours', 'ext_revenue_paid')) {
            $kpis[] = $kpi('revenue_per_billable_hour', 'eur', fn () => $v('ext_billable_hours') !== 0.0 ? $v('ext_revenue_paid') / $v('ext_billable_hours') : throw new \DivisionByZeroError);
        }

        return $kpis;
    }
}
