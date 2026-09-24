<?php

namespace Modules\DataPlatform\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * KI-gestützte Steuerung (erste Ausbaustufe): regelbasierte Insights über
 * die operativen Tabellen des Tenants. Regeln sind austauschbar — später
 * kann dieselbe Schnittstelle von einem LLM-Service befüllt werden.
 */
class InsightController extends Controller
{
    public function index()
    {
        return collect($this->rules())
            ->filter()
            ->sortBy(fn ($i) => array_search($i['severity'], ['critical', 'warning', 'info']))
            ->values();
    }

    private function rules(): array
    {
        $t = tenant()->getTenantKey();
        $count = fn (string $table, ?callable $scope = null) => DB::table($table)
            ->where('tenant_id', $t)
            ->when($scope, fn ($q) => $q->where($scope))
            ->count();

        $insights = [];

        $overdue = $count('tasks', fn ($q) => $q->whereIn('status', ['open', 'in_progress'])->where('due_at', '<', now()));
        if ($overdue) {
            $insights[] = $this->hit('warning', 'tasks_overdue', "{$overdue} Aufgabe(n) überfällig.", ['count' => $overdue]);
        }

        $instructions = $count('instructions');
        $done = $count('instructions', fn ($q) => $q->where('status', 'completed'));
        if ($instructions && ($done / $instructions) < 0.8) {
            $rate = round($done / $instructions * 100);
            $insights[] = $this->hit('warning', 'compliance_rate_low', "Schulungsquote bei {$rate}% — Ziel ≥ 80%.", ['rate' => $rate]);
        }

        $high = $count('risk_assessments', fn ($q) => $q->where('risk_level', 'high'));
        if ($high) {
            $insights[] = $this->hit('critical', 'high_risks_open', "{$high} Gefährdungsbeurteilung(en) mit hohem Risiko.", ['count' => $high]);
        }

        $dl = $count('deadlines', fn ($q) => $q->where('status', 'open')->whereDate('due_at', '<', now()));
        if ($dl) {
            $insights[] = $this->hit('critical', 'deadlines_overdue', "{$dl} offene Frist(en) überschritten.", ['count' => $dl]);
        }

        $openTenders = $count('tenders', fn ($q) => $q->where('status', 'open'));
        if ($openTenders) {
            $insights[] = $this->hit('info', 'tenders_open', "{$openTenders} Ausschreibung(en) offen — Experten-Matching prüfen.", ['count' => $openTenders]);
        }

        if (! $insights) {
            $insights[] = $this->hit('info', 'all_clear', 'Keine Auffälligkeiten — alle Kennzahlen im grünen Bereich.');
        }

        return $insights;
    }

    private function hit(string $severity, string $code, string $message, array $data = []): array
    {
        return compact('severity', 'code', 'message', 'data');
    }
}
