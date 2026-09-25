<?php

namespace Modules\Ai\Support;

use Illuminate\Support\Facades\DB;
use Modules\Ai\Contracts\AnalysisProvider;

class AiService
{
    private AnalysisProvider $provider;

    public function __construct()
    {
        $this->provider = new HeuristicAnalysisProvider;
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

    private function context(): array
    {
        $t = tenant()->getTenantKey();
        $count = fn (string $table, ?callable $scope = null) => DB::table($table)
            ->where('tenant_id', $t)
            ->when($scope, fn ($q) => $q->where($scope))
            ->count();

        return [
            'companies' => $count('companies'),
            'persons' => $count('persons'),
            'documents' => $count('documents'),
            'tasks_overdue' => $count('tasks', fn ($q) => $q->whereIn('status', ['open', 'in_progress'])->where('due_at', '<', now())),
            'instructions' => $count('instructions'),
            'instructions_completed' => $count('instructions', fn ($q) => $q->where('status', 'completed')),
            'risk_high' => $count('risk_assessments', fn ($q) => $q->where('risk_level', 'high')),
            'deadlines_overdue' => $count('deadlines', fn ($q) => $q->where('status', 'open')->where('due_at', '<', now())),
            'tenders_open' => $count('tenders', fn ($q) => $q->where('status', 'open')),
            'events_24h' => DB::table('stored_events')->where('created_at', '>', now()->subDay())->count(),
        ];
    }
}
