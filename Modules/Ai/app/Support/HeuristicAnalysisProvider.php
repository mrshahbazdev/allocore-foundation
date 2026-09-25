<?php

namespace Modules\Ai\Support;

use Modules\Ai\Contracts\AnalysisProvider;

/**
 * Default AI-layer provider: deterministic heuristic analysis over the
 * platform context. Requires no external services (R2 cloudunabhaengig).
 * An LLM-backed provider can be plugged in via the same interface later.
 */
class HeuristicAnalysisProvider implements AnalysisProvider
{
    public function name(): string
    {
        return 'heuristic';
    }

    public function analyze(array $context): array
    {
        $findings = [];

        $highRisks = $context['risk_high'] ?? 0;
        if ($highRisks > 0) {
            $findings[] = [
                'severity' => 'critical',
                'code' => 'high_risks',
                'message' => "{$highRisks} Gefährdungsbeurteilung(en) mit hohem Risiko — sofortiger Handlungsbedarf.",
            ];
        }

        $deadlines = $context['deadlines_overdue'] ?? 0;
        if ($deadlines > 0) {
            $findings[] = [
                'severity' => 'critical',
                'code' => 'deadlines_overdue',
                'message' => "{$deadlines} Frist(en) überschritten.",
            ];
        }

        $overdueTasks = $context['tasks_overdue'] ?? 0;
        if ($overdueTasks > 0) {
            $findings[] = [
                'severity' => 'warning',
                'code' => 'tasks_overdue',
                'message' => "{$overdueTasks} Aufgabe(n) überfällig.",
            ];
        }

        $instructions = $context['instructions'] ?? 0;
        $completed = $context['instructions_completed'] ?? 0;
        if ($instructions > 0 && ($completed / $instructions) < 0.8) {
            $rate = round($completed / $instructions * 100);
            $findings[] = [
                'severity' => 'warning',
                'code' => 'compliance_rate_low',
                'message' => "Schulungsquote bei {$rate}% — Ziel ≥ 80%.",
            ];
        }

        $openTenders = $context['tenders_open'] ?? 0;
        if ($openTenders > 0) {
            $findings[] = [
                'severity' => 'info',
                'code' => 'tenders_open',
                'message' => "{$openTenders} Ausschreibung(en) offen — Experten-Matching prüfen.",
            ];
        }

        $companies = $context['companies'] ?? 0;
        $persons = $context['persons'] ?? 0;
        $events = $context['events_24h'] ?? 0;

        $summary = sprintf(
            'Tenant-Analyse: %d Unternehmen, %d Personen, %d Aktivitaetsereignisse (24h). '.
            '%d kritische und %d Warnhinweise erkannt.',
            $companies,
            $persons,
            $events,
            count(array_filter($findings, fn ($f) => $f['severity'] === 'critical')),
            count(array_filter($findings, fn ($f) => $f['severity'] === 'warning')),
        );

        return ['summary' => $summary, 'findings' => $findings];
    }
}
