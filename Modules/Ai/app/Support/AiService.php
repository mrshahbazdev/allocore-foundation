<?php

namespace Modules\Ai\Support;

use Illuminate\Support\Facades\DB;
use Modules\Ai\Contracts\AnalysisProvider;
use Modules\DataPlatform\Support\KpiService;

class AiService
{
    private AnalysisProvider $provider;

    public function __construct()
    {
        $provider = config('services.ai.provider', 'heuristic');

        if ($provider === 'llm' && config('services.ai.api_key')) {
            $this->provider = new LlmAnalysisProvider;
        } else {
            $this->provider = new HeuristicAnalysisProvider;
        }
    }

    public function provider(): AnalysisProvider
    {
        return $this->provider;
    }

    public function analyze(): array
    {
        $result = $this->provider->analyze($this->context());

        return [
            'provider' => $this->provider->name(),
            'summary' => $result['summary'],
            'findings' => $result['findings'],
        ];
    }

    /**
     * Coach-Kontext nur aus der anonymisierten Schicht: Metrik-Snapshots
     * (Aggregatwerte), abgeleitete KPIs und pseudonymisierte Zwillinge —
     * keine Datensätze mit personenbezogenen Kennungen.
     */
    private function context(): array
    {
        $t = tenant()->getTenantKey();

        $metrics = DB::table('metric_snapshots')
            ->where('tenant_id', $t)
            ->orderByDesc('id')
            ->get(['metric', 'value'])
            ->unique('metric')
            ->pluck('value', 'metric')
            ->all();

        $kpis = collect(KpiService::derived($t, null))
            ->mapWithKeys(fn (array $k) => [$k['key'] => $k['value']])
            ->all();

        $anon = DB::table('anonymized_records')->where('tenant_id', $t)->get();
        $roles = $anon->where('source_type', 'user')
            ->flatMap(fn ($r) => (array) (json_decode(json_encode($r->payload), true)['roles'] ?? []))
            ->countBy()
            ->all();

        $agg = fn (string $table, ?callable $scope = null) => DB::table($table)
            ->where('tenant_id', $t)
            ->when($scope, fn ($q) => $q->where($scope))
            ->count();
        $m = fn (string $key, int $fallback) => (int) ($metrics[$key] ?? $fallback);

        return [
            'companies' => $m('companies', $agg('companies')),
            'persons' => $m('persons', $agg('persons')),
            'documents' => $m('documents', $agg('documents')),
            'tasks_overdue' => $agg('tasks', fn ($q) => $q->whereIn('status', ['open', 'in_progress'])->where('due_at', '<', now())),
            'instructions' => $m('instructions', $agg('instructions')),
            'instructions_completed' => $m('instructions', $agg('instructions')) - $m('instructions_pending', $agg('instructions', fn ($q) => $q->where('status', 'pending'))),
            'risk_high' => $m('risk_high', $agg('risk_assessments', fn ($q) => $q->where('risk_level', 'high'))),
            'deadlines_overdue' => $agg('deadlines', fn ($q) => $q->where('status', 'open')->where('due_at', '<', now())),
            'tenders_open' => $m('tenders_open', $agg('tenders', fn ($q) => $q->where('status', 'open'))),
            'events_24h' => DB::table('stored_events')
                ->where('meta_data->tenant_id', $t)
                ->where('created_at', '>', now()->subDay())
                ->count(),
            'metrics' => $metrics,
            'kpis' => $kpis,
            'anonymized' => [
                'total' => $anon->count(),
                'by_source' => $anon->countBy('source_type')->all(),
                'member_roles' => $roles,
            ],
        ];
    }
}
