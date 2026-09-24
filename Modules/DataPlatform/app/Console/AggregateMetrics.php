<?php

namespace Modules\DataPlatform\Console;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\DataPlatform\Models\MetricSnapshot;

/**
 * Phase 5 DWH layer: snapshots per-tenant KPIs daily so dashboards
 * can read cheap aggregates instead of scanning operational tables.
 */
class AggregateMetrics extends Command
{
    protected $signature = 'analytics:aggregate {--date= : Snapshot date (default: today)}';

    protected $description = 'Aggregiert tenant-weite KPIs in metric_snapshots (Data-Warehouse-Schicht).';

    private const COUNTS = [
        'companies' => 'companies',
        'persons' => 'persons',
        'documents' => 'documents',
        'tasks' => 'tasks',
        'tasks_open' => ['tasks', "status IN ('open','in_progress')"],
        'instructions' => 'instructions',
        'inspections' => 'inspections',
        'deadlines_open' => ['deadlines', "status = 'open'"],
        'risk_assessments' => 'risk_assessments',
        'risk_high' => ['risk_assessments', "risk_level = 'high'"],
        'operating_instructions' => 'operating_instructions',
        'expert_profiles' => 'expert_profiles',
        'questions' => 'questions',
        'tenders_open' => ['tenders', "status = 'open'"],
        'persons_on_leave' => ['leave_requests', "status = 'approved' AND starts_on <= date('now') AND ends_on >= date('now')"],
        'leave_requests_pending' => ['leave_requests', "status = 'pending'"],
        'machines_active' => ['machines', "status = 'active'"],
        'production_orders_open' => ['production_orders', "status IN ('queued','running')"],
        'production_scrap' => ['production_orders', 'scrap_qty > 0'],
        'participations_active' => ['participations', "status = 'active'"],
        'portfolios' => 'portfolios',
        'investments_active' => ['investments', 'disposed_at IS NULL'],
        'strategies' => 'strategies',
        'projects' => 'projects',
        'projects_open' => ['projects', "status IN ('planned','active','on_hold')"],
        'measures' => 'measures',
        'measures_open' => ['measures', "status IN ('open','in_progress')"],
    ];

    public function handle(): int
    {
        $date = $this->option('date') ?? today()->toDateString();
        $written = 0;

        foreach (Tenant::all() as $tenant) {
            foreach (self::COUNTS as $metric => $spec) {
                [$table, $where] = is_array($spec) ? $spec : [$spec, '1=1'];

                $value = DB::table($table)->where('tenant_id', $tenant->getTenantKey())->whereRaw($where)->count();

                MetricSnapshot::withoutGlobalScopes()->updateOrCreate(
                    ['tenant_id' => $tenant->getTenantKey(), 'metric' => $metric, 'captured_on' => $date],
                    ['value' => $value],
                );
                $written++;
            }

            // compliance rate: completed instructions / total instructions
            $total = DB::table('instructions')->where('tenant_id', $tenant->getTenantKey())->count();
            $done = DB::table('instructions')->where('tenant_id', $tenant->getTenantKey())->where('status', 'completed')->count();

            MetricSnapshot::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenant->getTenantKey(), 'metric' => 'compliance_rate', 'captured_on' => $date],
                ['value' => $total ? round($done / $total * 100, 2) : 0],
            );
            $written++;
        }

        $this->info("{$written} Metriken geschrieben fuer {$date}.");

        return self::SUCCESS;
    }
}
