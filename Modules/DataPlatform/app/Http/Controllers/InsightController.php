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
        $count = fn (string $table, string $where = '1=1') => DB::table($table)
            ->where('tenant_id', $t)->whereRaw($where)->count();

        $insights = [];

        $overdue = $count('tasks', "status IN ('open','in_progress') AND due_at < datetime('now')");
        if ($overdue) {
            $insights[] = $this->hit('warning', 'tasks_overdue', "{$overdue} Aufgabe(n) überfällig.", ['count' => $overdue]);
        }

        $instructions = $count('instructions');
        $done = $count('instructions', "status = 'completed'");
        if ($instructions && ($done / $instructions) < 0.8) {
            $rate = round($done / $instructions * 100);
            $insights[] = $this->hit('warning', 'compliance_rate_low', "Schulungsquote bei {$rate}% — Ziel ≥ 80%.", ['rate' => $rate]);
        }

        $high = $count('risk_assessments', "risk_level = 'high'");
        if ($high) {
            $insights[] = $this->hit('critical', 'high_risks_open', "{$high} Gefährdungsbeurteilung(en) mit hohem Risiko.", ['count' => $high]);
        }

        $dl = $count('deadlines', "status = 'open' AND due_at < date('now')");
        if ($dl) {
            $insights[] = $this->hit('critical', 'deadlines_overdue', "{$dl} offene Frist(en) überschritten.", ['count' => $dl]);
        }

        $openTenders = $count('tenders', "status = 'open'");
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
