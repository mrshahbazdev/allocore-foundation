<?php

namespace Modules\Executive\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Executive\Models\ExecReport;

/**
 * Layer 9 Executive Layer: cross-tenant (Holding-level) rollup.
 * The caller authenticates against one tenant (where they hold
 * executive.view) and receives group-wide metrics across all tenants.
 */
class ExecutiveController extends Controller
{
    public function overview()
    {
        $rows = [];
        $totals = array_fill_keys([
            'companies', 'persons', 'tasks_open', 'deadlines_open',
            'high_risks', 'data_objects',
        ], 0);

        foreach (Tenant::all() as $tenant) {
            $key = $tenant->getTenantKey();
            $row = [
                'id' => $key,
                'name' => $tenant->name,
                'companies' => $this->count('companies', $key),
                'persons' => $this->count('persons', $key),
                'tasks_open' => $this->count('tasks', $key, "status IN ('open','in_progress')"),
                'deadlines_open' => $this->count('deadlines', $key, "status = 'open'"),
                'high_risks' => $this->count('risk_assessments', $key, "risk_level = 'high'"),
                'data_objects' => $this->count('data_objects', $key),
            ];
            $row['compliance_rate'] = $this->complianceRate($key);

            foreach ($totals as $m => $_) {
                $totals[$m] += $row[$m];
            }
            $rows[] = $row;
        }

        return [
            'tenants' => $rows,
            'totals' => $totals + ['tenants' => count($rows)],
        ];
    }

    public function index()
    {
        return ExecReport::query()->latest('id')->get(['id', 'title', 'generated_by', 'created_at']);
    }

    public function store(Request $request)
    {
        $request->validate(['title' => ['sometimes', 'string', 'max:255']]);

        $report = ExecReport::create([
            'tenant_id' => tenant()->getTenantKey(),
            'title' => $request->input('title', 'Executive Report'),
            'generated_by' => $request->user()?->id,
            'payload' => $this->overview(),
        ]);

        return response()->json($report, 201);
    }

    public function show(ExecReport $exec_report)
    {
        return $exec_report;
    }

    private function count(string $table, string $tenantKey, ?string $where = null): int
    {
        return DB::table($table)->where('tenant_id', $tenantKey)
            ->when($where, fn ($q) => $q->whereRaw($where))
            ->count();
    }

    private function complianceRate(string $tenantKey): float
    {
        $total = $this->count('instructions', $tenantKey);
        $done = $this->count('instructions', $tenantKey, "status = 'completed'");

        return $total ? round($done / $total * 100, 2) : 0.0;
    }
}
