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

        $unassigned = $count('tasks', fn ($q) => $q->whereIn('status', ['open', 'in_progress'])->whereNull('assignee_id'));
        if ($unassigned) {
            $insights[] = $this->hit('info', 'tasks_unassigned', "{$unassigned} offene Aufgabe(n) ohne Verantwortlichen.", ['count' => $unassigned]);
        }

        $soon = $count('tasks', fn ($q) => $q->whereIn('status', ['open', 'in_progress'])->whereBetween('due_at', [now(), now()->addDays(7)]));
        if ($soon) {
            $insights[] = $this->hit('info', 'tasks_due_soon', "{$soon} Aufgabe(n) innerhalb von 7 Tagen fällig.", ['count' => $soon]);
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

        $dlSoon = $count('deadlines', fn ($q) => $q->where('status', 'open')->whereBetween('due_at', [now(), now()->addDays(7)]));
        if ($dlSoon) {
            $insights[] = $this->hit('info', 'deadlines_due_soon', "{$dlSoon} Frist(en) innerhalb von 7 Tagen fällig.", ['count' => $dlSoon]);
        }

        $dlUnassigned = $count('deadlines', fn ($q) => $q->where('status', 'open')->whereNull('responsible_id'));
        if ($dlUnassigned) {
            $insights[] = $this->hit('info', 'deadlines_unassigned', "{$dlUnassigned} offene Frist(en) ohne Verantwortlichen.", ['count' => $dlUnassigned]);
        }

        $insp = $count('inspections', fn ($q) => $q->where('status', 'scheduled')->where('scheduled_at', '<', now()));
        if ($insp) {
            $insights[] = $this->hit('warning', 'inspections_overdue', "{$insp} geplante Prüfung(en) überfällig.", ['count' => $insp]);
        }

        $inspSoon = $count('inspections', fn ($q) => $q->where('status', 'scheduled')->whereBetween('scheduled_at', [now(), now()->addDays(7)]));
        if ($inspSoon) {
            $insights[] = $this->hit('info', 'inspections_due_soon', "{$inspSoon} Prüfung(en) innerhalb von 7 Tagen geplant.", ['count' => $inspSoon]);
        }

        $inspUnassigned = $count('inspections', fn ($q) => $q->where('status', 'scheduled')->whereNull('responsible_id'));
        if ($inspUnassigned) {
            $insights[] = $this->hit('info', 'inspections_unassigned', "{$inspUnassigned} geplante Prüfung(en) ohne Verantwortlichen.", ['count' => $inspUnassigned]);
        }

        $instrSoon = $count('instructions', fn ($q) => $q->where('status', 'pending')->whereBetween('due_at', [now(), now()->addDays(7)]));
        if ($instrSoon) {
            $insights[] = $this->hit('info', 'instructions_due_soon', "{$instrSoon} Unterweisung(en) innerhalb von 7 Tagen fällig.", ['count' => $instrSoon]);
        }

        $instrOverdue = $count('instructions', fn ($q) => $q->where('status', 'pending')->where('due_at', '<', now()));
        if ($instrOverdue) {
            $insights[] = $this->hit('warning', 'instructions_overdue', "{$instrOverdue} Unterweisung(en) überfällig.", ['count' => $instrOverdue]);
        }

        $instrUnassigned = $count('instructions', fn ($q) => $q->where('status', 'pending')->whereNull('responsible_id'));
        if ($instrUnassigned) {
            $insights[] = $this->hit('info', 'instructions_unassigned', "{$instrUnassigned} offene Unterweisung(en) ohne Verantwortlichen.", ['count' => $instrUnassigned]);
        }

        $reviewsDue = $count('risk_assessments', fn ($q) => $q->where('status', 'open')->where('review_at', '<', now()));
        if ($reviewsDue) {
            $insights[] = $this->hit('warning', 'risk_reviews_overdue', "{$reviewsDue} Gefährdungsbeurteilung(en) — Reviews überfällig.", ['count' => $reviewsDue]);
        }

        $reviewsSoon = $count('risk_assessments', fn ($q) => $q->where('status', 'open')->whereBetween('review_at', [now(), now()->addDays(7)]));
        if ($reviewsSoon) {
            $insights[] = $this->hit('info', 'risk_reviews_due_soon', "{$reviewsSoon} Gefährdungsbeurteilung(en) — Reviews innerhalb von 7 Tagen fällig.", ['count' => $reviewsSoon]);
        }

        $negLiquidity = DB::table('financial_reports')->where('tenant_id', $t)->where('period', now()->format('Y-m'))->where('liquidity', '<', 0)->count();
        if ($negLiquidity) {
            $insights[] = $this->hit('critical', 'fin_negative_liquidity', "{$negLiquidity} Unternehmen mit negativer Liquidität im laufenden Monat.", ['count' => $negLiquidity]);
        }

        $missingReports = DB::table('companies')->where('tenant_id', $t)
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('financial_reports')
                ->whereColumn('financial_reports.company_id', 'companies.id')
                ->where('financial_reports.tenant_id', $t)
                ->where('period', now()->format('Y-m')))->count();
        if ($missingReports) {
            $insights[] = $this->hit('info', 'fin_reports_missing', "{$missingReports} Unternehmen ohne Finanzbericht für den laufenden Monat.", ['count' => $missingReports]);
        }

        $leave = $count('leave_requests', fn ($q) => $q->where('status', 'pending'));
        if ($leave) {
            $insights[] = $this->hit('info', 'leave_requests_pending', "{$leave} Urlaubs-/Fehlzeitenantrag/-anträge zur Genehmigung offen.", ['count' => $leave]);
        }

        $leaveStale = $count('leave_requests', fn ($q) => $q->where('status', 'pending')->where('created_at', '<', now()->subDays(7)));
        if ($leaveStale) {
            $insights[] = $this->hit('warning', 'leave_pending_stale', "{$leaveStale} Urlaubs-/Fehlzeitenantrag/-anträge seit über 7 Tagen unbeantwortet.", ['count' => $leaveStale]);
        }

        $leaveActive = $count('leave_requests', fn ($q) => $q->where('status', 'approved')->whereDate('starts_on', '<=', now())->whereDate('ends_on', '>=', now()));
        if ($leaveActive) {
            $insights[] = $this->hit('info', 'leave_active_today', "{$leaveActive} genehmigte(r) Urlaubs-/Fehlzeitenantrag/-anträge läuft/laufen heute.", ['count' => $leaveActive]);
        }

        $ordersOverdue = $count('production_orders', fn ($q) => $q->whereIn('status', ['queued', 'running'])->where('due_at', '<', now()));
        if ($ordersOverdue) {
            $insights[] = $this->hit('warning', 'orders_overdue', "{$ordersOverdue} Produktionsauftrag/-aufträge überfällig.", ['count' => $ordersOverdue]);
        }

        $ordersSoon = $count('production_orders', fn ($q) => $q->whereIn('status', ['queued', 'running'])->whereBetween('due_at', [now(), now()->addDays(7)]));
        if ($ordersSoon) {
            $insights[] = $this->hit('info', 'orders_due_soon', "{$ordersSoon} Produktionsauftrag/-aufträge — Fälligkeit in ≤7 Tagen.", ['count' => $ordersSoon]);
        }

        $ordersUnassigned = $count('production_orders', fn ($q) => $q->whereIn('status', ['queued', 'running'])->whereNull('assigned_to'));
        if ($ordersUnassigned) {
            $insights[] = $this->hit('info', 'orders_unassigned', "{$ordersUnassigned} Produktionsauftrag/-aufträge ohne Zuständige.", ['count' => $ordersUnassigned]);
        }

        $measuresOverdue = $count('measures', fn ($q) => $q->whereIn('status', ['open', 'in_progress'])->where('due_at', '<', now()));
        if ($measuresOverdue) {
            $insights[] = $this->hit('warning', 'measures_overdue', "{$measuresOverdue} Maßnahme(n) überfällig.", ['count' => $measuresOverdue]);
        }

        $measuresSoon = $count('measures', fn ($q) => $q->whereIn('status', ['open', 'in_progress'])->whereBetween('due_at', [now(), now()->addDays(7)]));
        if ($measuresSoon) {
            $insights[] = $this->hit('info', 'measures_due_soon', "{$measuresSoon} Maßnahme(n) — Fälligkeit in ≤7 Tagen.", ['count' => $measuresSoon]);
        }

        $measuresUnassigned = $count('measures', fn ($q) => $q->whereIn('status', ['open', 'in_progress'])->whereNull('responsible_id'));
        if ($measuresUnassigned) {
            $insights[] = $this->hit('info', 'measures_unassigned', "{$measuresUnassigned} offene Maßnahme(n) ohne Verantwortlichen.", ['count' => $measuresUnassigned]);
        }

        $projectsOverdue = $count('projects', fn ($q) => $q->whereIn('status', ['planned', 'active', 'on_hold'])->where('ends_at', '<', now()));
        if ($projectsOverdue) {
            $insights[] = $this->hit('warning', 'projects_overdue', "{$projectsOverdue} Projekt(e) über Enddatum hinaus aktiv.", ['count' => $projectsOverdue]);
        }

        $projectsSoon = $count('projects', fn ($q) => $q->whereIn('status', ['planned', 'active', 'on_hold'])->whereBetween('ends_at', [now(), now()->addDays(7)]));
        if ($projectsSoon) {
            $insights[] = $this->hit('info', 'projects_ending_soon', "{$projectsSoon} Projekt(e) — Enddatum in ≤7 Tagen.", ['count' => $projectsSoon]);
        }

        $projectsUnassigned = $count('projects', fn ($q) => $q->whereIn('status', ['planned', 'active', 'on_hold'])->whereNull('owner_id'));
        if ($projectsUnassigned) {
            $insights[] = $this->hit('info', 'projects_unassigned', "{$projectsUnassigned} Projekt(e) ohne Verantwortlichen.", ['count' => $projectsUnassigned]);
        }

        $machinesDown = $count('machines', fn ($q) => $q->where('status', 'maintenance'));
        if ($machinesDown) {
            $insights[] = $this->hit('warning', 'machines_in_maintenance', "{$machinesDown} Maschine(n) in Wartung — Produktionskapazität prüfen.", ['count' => $machinesDown]);
        }

        $openTenders = $count('tenders', fn ($q) => $q->where('status', 'open'));
        if ($openTenders) {
            $insights[] = $this->hit('info', 'tenders_open', "{$openTenders} Ausschreibung(en) offen — Experten-Matching prüfen.", ['count' => $openTenders]);
        }

        $tendersOverdue = $count('tenders', fn ($q) => $q->where('status', 'open')->where('deadline_at', '<', now()));
        if ($tendersOverdue) {
            $insights[] = $this->hit('critical', 'tenders_overdue', "{$tendersOverdue} Ausschreibung(en) über Bewerbungsfrist — vergeben oder schließen.", ['count' => $tendersOverdue]);
        }

        $tendersSoon = $count('tenders', fn ($q) => $q->where('status', 'open')->whereBetween('deadline_at', [now(), now()->addDays(7)]));
        if ($tendersSoon) {
            $insights[] = $this->hit('warning', 'tenders_deadline_soon', "{$tendersSoon} Ausschreibung(en) — Bewerbungsfrist endet in ≤7 Tagen.", ['count' => $tendersSoon]);
        }

        $tasksDoneNoStamp = $count('tasks', fn ($q) => $q->where('status', 'done')->whereNull('completed_at'));
        if ($tasksDoneNoStamp) {
            $insights[] = $this->hit('info', 'tasks_done_no_stamp', "{$tasksDoneNoStamp} erledigte Aufgabe(n) ohne Erledigt-Zeitpunkt.", ['count' => $tasksDoneNoStamp]);
        }

        $dataObjectsEmpty = $count('data_objects', fn ($q) => $q->where('size_bytes', 0));
        if ($dataObjectsEmpty) {
            $insights[] = $this->hit('info', 'data_objects_empty', "{$dataObjectsEmpty} Data-Lake-Objekt(e) mit Größe 0 B.", ['count' => $dataObjectsEmpty]);
        }

        $auditsNoAuditor = $count('audits', fn ($q) => $q->whereIn('status', ['planned', 'in_progress'])->whereNull('auditor'));
        if ($auditsNoAuditor) {
            $insights[] = $this->hit('warning', 'audits_no_auditor', "{$auditsNoAuditor} geplante/laufende(s) Audit(s) ohne benannten Auditor.", ['count' => $auditsNoAuditor]);
        }

        $applicationsNoProposal = $count('tender_applications', fn ($q) => $q->whereNull('proposal'));
        if ($applicationsNoProposal) {
            $insights[] = $this->hit('info', 'applications_no_proposal', "{$applicationsNoProposal} Bewerbung(en) ohne Angebotstext.", ['count' => $applicationsNoProposal]);
        }

        $riskHighNoMeasures = $count('risk_assessments', fn ($q) => $q->whereIn('status', ['open', 'in_progress'])->whereIn('risk_level', ['high', 'critical'])->whereNull('measures'));
        if ($riskHighNoMeasures) {
            $insights[] = $this->hit('critical', 'risks_no_measures', "{$riskHighNoMeasures} hohe/kritische(r) Risiko(risiken) ohne Maßnahmen.", ['count' => $riskHighNoMeasures]);
        }

        $deadlinesCompletedNoStamp = $count('deadlines', fn ($q) => $q->where('status', 'completed')->whereNull('completed_at'));
        if ($deadlinesCompletedNoStamp) {
            $insights[] = $this->hit('info', 'deadlines_completed_no_stamp', "{$deadlinesCompletedNoStamp} erledigte(r) Frist(en) ohne Erledigt-Zeitpunkt.", ['count' => $deadlinesCompletedNoStamp]);
        }

        $leaveDecidedNoStamp = $count('leave_requests', fn ($q) => $q->whereIn('status', ['approved', 'rejected'])->whereNull('decided_at'));
        if ($leaveDecidedNoStamp) {
            $insights[] = $this->hit('info', 'leave_decided_no_stamp', "{$leaveDecidedNoStamp} entschiedene(r) Urlaubsantrag/-anträge ohne Entscheidungsdatum.", ['count' => $leaveDecidedNoStamp]);
        }

        $partExitedStake = $count('participations', fn ($q) => $q->where('status', 'exited')->where('stake_pct', '>', 0));
        if ($partExitedStake) {
            $insights[] = $this->hit('warning', 'participations_exited_with_stake', "{$partExitedStake} beendete Beteiligung(en) mit Anteil > 0 %.", ['count' => $partExitedStake]);
        }

        $projectsNoStrategy = $count('projects', fn ($q) => $q->whereIn('status', ['planned', 'active'])->whereNull('strategy_id'));
        if ($projectsNoStrategy) {
            $insights[] = $this->hit('warning', 'projects_no_strategy', "{$projectsNoStrategy} offene(s) Projekt(e) ohne zugeordnete Strategie.", ['count' => $projectsNoStrategy]);
        }

        $invNoValue = $count('investments', fn ($q) => $q->whereNull('disposed_at')->whereNull('current_value'));
        if ($invNoValue) {
            $insights[] = $this->hit('info', 'investments_no_value', "{$invNoValue} aktive Investition(en) ohne aktuellen Wert.", ['count' => $invNoValue]);
        }

        $ordersRunningNoStart = $count('production_orders', fn ($q) => $q->where('status', 'running')->whereNull('started_at'));
        if ($ordersRunningNoStart) {
            $insights[] = $this->hit('info', 'orders_running_no_start', "{$ordersRunningNoStart} laufende(r) Auftrag/Aufträge ohne Startzeit.", ['count' => $ordersRunningNoStart]);
        }

        $ordersDoneNoFinish = $count('production_orders', fn ($q) => $q->where('status', 'done')->whereNull('finished_at'));
        if ($ordersDoneNoFinish) {
            $insights[] = $this->hit('info', 'orders_done_no_finish', "{$ordersDoneNoFinish} fertige(r) Auftrag/Aufträge ohne Endzeit.", ['count' => $ordersDoneNoFinish]);
        }

        $questionsNoAccepted = $count('questions', fn ($q) => $q->where('status', 'answered')->whereNotExists(fn ($sub) => $sub->selectRaw(1)->from('answers')->whereColumn('answers.question_id', 'questions.id')->where('answers.is_accepted', true)));
        if ($questionsNoAccepted) {
            $insights[] = $this->hit('info', 'questions_no_accepted', "{$questionsNoAccepted} beantwortete Frage(n) ohne akzeptierte Antwort.", ['count' => $questionsNoAccepted]);
        }

        $projectsDoneGap = $count('projects', fn ($q) => $q->where('status', 'done')->where('progress', '<', 100));
        if ($projectsDoneGap) {
            $insights[] = $this->hit('warning', 'projects_done_incomplete', "{$projectsDoneGap} als erledigt markierte(s) Projekt(e) mit Fortschritt <100%.", ['count' => $projectsDoneGap]);
        }

        $ordersDoneNoQty = $count('production_orders', fn ($q) => $q->where('status', 'done')->where('scrap_qty', '>', 0));
        if ($ordersDoneNoQty) {
            $insights[] = $this->hit('warning', 'orders_done_incomplete', "{$ordersDoneNoQty} fertige(r) Auftrag/Aufträge mit Ausschuss.", ['count' => $ordersDoneNoQty]);
        }

        $tendersAwardGap = $count('tenders', fn ($q) => $q->where('status', 'awarded')->whereNotExists(fn ($sub) => $sub->selectRaw(1)->from('tender_applications')->whereColumn('tender_applications.tender_id', 'tenders.id')->where('tender_applications.status', 'awarded')));
        if ($tendersAwardGap) {
            $insights[] = $this->hit('warning', 'tenders_awarded_no_winner', "{$tendersAwardGap} vergebene(r) Ausschreibung(en) ohne vergebene Bewerbung.", ['count' => $tendersAwardGap]);
        }

        $opInstrNoDoc = $count('operating_instructions', fn ($q) => $q->whereNull('document_id'));
        if ($opInstrNoDoc) {
            $insights[] = $this->hit('info', 'op_instructions_no_document', "{$opInstrNoDoc} Betriebsanweisung(en) ohne verknüpftes Dokument.", ['count' => $opInstrNoDoc]);
        }

        $instrNoDoc = $count('instructions', fn ($q) => $q->whereNull('document_id'));
        if ($instrNoDoc) {
            $insights[] = $this->hit('info', 'instructions_no_document', "{$instrNoDoc} Unterweisung(en) ohne verknüpftes Dokument.", ['count' => $instrNoDoc]);
        }

        $questionsNoAnswer = $count('questions', fn ($q) => $q->whereIn('status', ['answered', 'closed'])->whereNotExists(fn ($sub) => $sub->selectRaw(1)->from('answers')->whereColumn('answers.question_id', 'questions.id')));
        if ($questionsNoAnswer) {
            $insights[] = $this->hit('info', 'questions_no_answers', "{$questionsNoAnswer} Frage(n) beantwortet/geschlossen ohne Antwort.", ['count' => $questionsNoAnswer]);
        }

        $appsStale = $count('tender_applications', fn ($q) => $q->where('status', 'submitted')->where('created_at', '<', now()->subDays(14)));
        if ($appsStale) {
            $insights[] = $this->hit('info', 'applications_stale', "{$appsStale} Bewerbung(en) seit >14 Tagen unentschieden.", ['count' => $appsStale]);
        }

        $auditsNoResult = $count('audits', fn ($q) => $q->where('status', 'done')->whereNull('result'));
        if ($auditsNoResult) {
            $insights[] = $this->hit('info', 'audits_no_result', "{$auditsNoResult} abgeschlossene(s) Audit(s) ohne Ergebnis.", ['count' => $auditsNoResult]);
        }

        $instrNoDate = $count('instructions', fn ($q) => $q->where('status', 'completed')->whereNull('completed_at'));
        if ($instrNoDate) {
            $insights[] = $this->hit('info', 'instructions_no_completed_at', "{$instrNoDate} abgeschlossene Unterweisung(en) ohne Abschlussdatum.", ['count' => $instrNoDate]);
        }

        $objNoCat = $count('data_objects', fn ($q) => $q->whereNull('category')->orWhere('category', ''));
        if ($objNoCat) {
            $insights[] = $this->hit('info', 'data_objects_no_category', "{$objNoCat} Data-Lake-Objekt(e) ohne Kategorie.", ['count' => $objNoCat]);
        }

        $edgesNoRel = $count('graph_edges', fn ($q) => $q->whereNull('relation')->orWhere('relation', ''));
        if ($edgesNoRel) {
            $insights[] = $this->hit('info', 'graph_edges_no_relation', "{$edgesNoRel} Graph-Kante(n) ohne Relationsbezeichnung.", ['count' => $edgesNoRel]);
        }

        $renewals = $count('instructions', fn ($q) => $q->where('status', 'completed')->whereNotNull('interval_months')->whereRaw('DATE_ADD(completed_at, INTERVAL interval_months MONTH) < ?', [now()]));
        if ($renewals) {
            $insights[] = $this->hit('warning', 'instructions_renewal_due', "{$renewals} Unterweisung(en) – Wiederholungsintervall überschritten.", ['count' => $renewals]);
        }

        $inspNoPerson = $count('inspections', fn ($q) => $q->where('status', 'scheduled')->whereNull('person_id')->whereNull('responsible_id'));
        if ($inspNoPerson) {
            $insights[] = $this->hit('info', 'inspections_no_person', "{$inspNoPerson} geplante Prüfung(en) ohne zugeordnete Person.", ['count' => $inspNoPerson]);
        }

        $mNoProject = $count('measures', fn ($q) => $q->whereNull('project_id'));
        if ($mNoProject) {
            $insights[] = $this->hit('info', 'measures_no_project', "{$mNoProject} Maßnahme(n) ohne zugeordnetes Projekt.", ['count' => $mNoProject]);
        }

        $mZeroCap = $count('machines', fn ($q) => $q->where('status', 'active')->where('capacity_units_per_day', '<=', 0));
        if ($mZeroCap) {
            $insights[] = $this->hit('info', 'machines_zero_capacity', "{$mZeroCap} aktive Maschine(n) ohne Kapazität.", ['count' => $mZeroCap]);
        }

        $pStalled = $count('projects', fn ($q) => $q->where('status', 'active')->where('progress', 0)->where('created_at', '<', now()->subDays(30)));
        if ($pStalled) {
            $insights[] = $this->hit('warning', 'projects_stalled', "{$pStalled} aktive(s) Projekt(e) seit >30 Tagen ohne Fortschritt.", ['count' => $pStalled]);
        }

        $tNoBudget = $count('tenders', fn ($q) => $q->where('status', 'open')->whereNull('budget'));
        if ($tNoBudget) {
            $insights[] = $this->hit('info', 'tenders_no_budget', "{$tNoBudget} offene Ausschreibung(en) ohne Budgetangabe.", ['count' => $tNoBudget]);
        }

        $qNoExpert = $count('questions', fn ($q) => $q->where('status', 'open')->whereNull('expert_profile_id'));
        if ($qNoExpert) {
            $insights[] = $this->hit('info', 'questions_unassigned', "{$qNoExpert} offene Frage(n) ohne zugeordneten Experten.", ['count' => $qNoExpert]);
        }

        $aiFailed = $count('ai_analyses', fn ($q) => $q->where('status', 'failed'));
        if ($aiFailed) {
            $insights[] = $this->hit('warning', 'ai_analyses_failed', "{$aiFailed} KI-Analyse(n) mit Fehlerstatus.", ['count' => $aiFailed]);
        }

        $dlNoSubject = $count('deadlines', fn ($q) => $q->where('status', 'open')->whereNull('subject_id'));
        if ($dlNoSubject) {
            $insights[] = $this->hit('info', 'deadlines_no_subject', "{$dlNoSubject} offene Frist(en) ohne verknüpften Datensatz.", ['count' => $dlNoSubject]);
        }

        $gbNoPerson = $count('risk_assessments', fn ($q) => $q->where('status', 'open')->whereNull('person_id'));
        if ($gbNoPerson) {
            $insights[] = $this->hit('info', 'risk_assessments_no_person', "{$gbNoPerson} offene Gefährdungsbeurteilung(en) ohne zugeordnete Person.", ['count' => $gbNoPerson]);
        }

        $auditsNoFindings = $count('audits', fn ($q) => $q->where('status', 'done')->whereNotExists(fn ($sub) => $sub->selectRaw(1)->from('audit_findings')->whereColumn('audit_findings.audit_id', 'audits.id')));
        if ($auditsNoFindings) {
            $insights[] = $this->hit('info', 'audits_no_findings', "{$auditsNoFindings} abgeschlossene(s) Audit(s) ohne Feststellungen.", ['count' => $auditsNoFindings]);
        }

        $appNoPrice = DB::table('tender_applications')->where('tenant_id', $t)
            ->whereNull('price')
            ->whereExists(fn ($sub) => $sub->selectRaw(1)->from('tenders')->whereColumn('tenders.id', 'tender_applications.tender_id')->where('status', 'open'))
            ->count();
        if ($appNoPrice) {
            $insights[] = $this->hit('info', 'applications_no_price', "{$appNoPrice} Bewerbung(en) auf offene Ausschreibungen ohne Preisangabe.", ['count' => $appNoPrice]);
        }

        $leaveOverlap = DB::table('leave_requests')->where('tenant_id', $t)->where('status', 'approved')
            ->whereExists(fn ($sub) => $sub->selectRaw(1)->from('leave_requests as l2')
                ->whereColumn('l2.person_id', 'leave_requests.person_id')
                ->whereColumn('l2.id', '!=', 'leave_requests.id')
                ->where('l2.status', 'approved')
                ->whereColumn('l2.starts_on', '<=', 'leave_requests.ends_on')
                ->whereColumn('l2.ends_on', '>=', 'leave_requests.starts_on'))->count();
        if ($leaveOverlap) {
            $insights[] = $this->hit('warning', 'leave_overlap', "{$leaveOverlap} genehmigte(r) Urlaubsantrag/-anträge überschneiden sich.", ['count' => $leaveOverlap]);
        }

        $persNoContact = $count('persons', fn ($q) => $q->whereNull('email')->whereNull('phone'));
        if ($persNoContact) {
            $insights[] = $this->hit('info', 'persons_no_contact', "{$persNoContact} Person(en) ohne E-Mail und Telefon.", ['count' => $persNoContact]);
        }

        $expNoRate = $count('expert_profiles', fn ($q) => $q->where('status', 'active')->whereNull('hourly_rate'));
        if ($expNoRate) {
            $insights[] = $this->hit('info', 'expert_profiles_no_rate', "{$expNoRate} aktive(s) Expertenprofil(e) ohne Stundensatz.", ['count' => $expNoRate]);
        }

        $docNoCat = $count('documents', fn ($q) => $q->whereNull('category')->orWhere('category', ''));
        if ($docNoCat) {
            $insights[] = $this->hit('info', 'documents_no_category', "{$docNoCat} Dokument(e) ohne Kategorie.", ['count' => $docNoCat]);
        }

        $invStale = $count('investments', fn ($q) => $q->whereNull('disposed_at')->whereNotNull('valued_at')->where('valued_at', '<', now()->subDays(90)));
        if ($invStale) {
            $insights[] = $this->hit('info', 'investments_stale_value', "{$invStale} Investition(en) mit Bewertung älter als 90 Tage.", ['count' => $invStale]);
        }

        $projNoOwner = $count('projects', fn ($q) => $q->whereIn('status', ['planned', 'active'])->whereNull('owner_id'));
        if ($projNoOwner) {
            $insights[] = $this->hit('warning', 'projects_no_owner', "{$projNoOwner} offene(s) Projekt(e) ohne Owner.", ['count' => $projNoOwner]);
        }

        $instrNoPerson = $count('instructions', fn ($q) => $q->whereIn('status', ['pending', 'overdue'])->whereNull('person_id'));
        if ($instrNoPerson) {
            $insights[] = $this->hit('warning', 'instructions_no_person', "{$instrNoPerson} offene Unterweisung(en) ohne zugewiesene Person.", ['count' => $instrNoPerson]);
        }

        $machinesIdle = $count('machines', fn ($q) => $q->where('status', 'active')->whereNotExists(fn ($sub) => $sub->selectRaw(1)->from('production_orders')->whereColumn('production_orders.machine_id', 'machines.id')->whereIn('status', ['queued', 'running'])));
        if ($machinesIdle) {
            $insights[] = $this->hit('info', 'machines_idle', "{$machinesIdle} aktive Maschine(n) ohne eingeplanten Auftrag.", ['count' => $machinesIdle]);
        }

        $ordersMachineMaintenance = $count('production_orders', fn ($q) => $q->whereIn('status', ['queued', 'running'])->whereNotNull('machine_id')->whereExists(fn ($sub) => $sub->selectRaw(1)->from('machines')->whereColumn('machines.id', 'production_orders.machine_id')->where('machines.status', 'maintenance')));
        if ($ordersMachineMaintenance) {
            $insights[] = $this->hit('warning', 'orders_machine_maintenance', "{$ordersMachineMaintenance} Auftrag/Aufträge auf Maschine(n) in Wartung eingeplant.", ['count' => $ordersMachineMaintenance]);
        }

        $questionsStale = $count('questions', fn ($q) => $q->where('status', 'open')->where('created_at', '<', now()->subDays(14)));
        if ($questionsStale) {
            $insights[] = $this->hit('warning', 'questions_stale', "{$questionsStale} Frage(n) seit >14 Tagen unbeantwortet.", ['count' => $questionsStale]);
        }

        $opInstrExpired = $count('operating_instructions', fn ($q) => $q->where('status', 'active')->whereNotNull('valid_from')->where('valid_from', '<', now()->subYear()));
        if ($opInstrExpired) {
            $insights[] = $this->hit('info', 'op_instructions_review', "{$opInstrExpired} Betriebsanweisung(en) älter als 1 Jahr — Review fällig.", ['count' => $opInstrExpired]);
        }

        $inspNoResult = $count('inspections', fn ($q) => $q->where('status', 'completed')->whereNull('result'));
        if ($inspNoResult) {
            $insights[] = $this->hit('info', 'inspections_no_result', "{$inspNoResult} abgeschlossene Prüfung(en) ohne Ergebnis-Eintrag.", ['count' => $inspNoResult]);
        }

        $ordersNoMachine = $count('production_orders', fn ($q) => $q->whereIn('status', ['queued', 'running'])->whereNull('machine_id'));
        if ($ordersNoMachine) {
            $insights[] = $this->hit('warning', 'orders_no_machine', "{$ordersNoMachine} aktive(r) Auftrag/Aufträge ohne Maschinen-Zuordnung.", ['count' => $ordersNoMachine]);
        }

        $companiesNoPersons = $count('companies', fn ($q) => $q->whereNotExists(fn ($sub) => $sub->selectRaw(1)->from('persons')->whereColumn('persons.company_id', 'companies.id')));
        if ($companiesNoPersons) {
            $insights[] = $this->hit('info', 'companies_no_persons', "{$companiesNoPersons} Unternehmen ohne Ansprechpartner.", ['count' => $companiesNoPersons]);
        }

        $personsNoCompany = $count('persons', fn ($q) => $q->whereNull('company_id'));
        if ($personsNoCompany) {
            $insights[] = $this->hit('info', 'persons_without_company', "{$personsNoCompany} Person(en) ohne Unternehmens-Zuordnung.", ['count' => $personsNoCompany]);
        }

        $docsNoVersion = $count('documents', fn ($q) => $q->whereNull('current_version_id'));
        if ($docsNoVersion) {
            $insights[] = $this->hit('info', 'documents_no_version', "{$docsNoVersion} Dokument(e) ohne Datei-Version — Inhalt hochladen.", ['count' => $docsNoVersion]);
        }

        $expertsNoSkills = $count('expert_profiles', fn ($q) => $q->where('status', 'active')->where(fn ($w) => $w->whereNull('skills')->orWhereJsonLength('skills', 0)));
        if ($expertsNoSkills) {
            $insights[] = $this->hit('info', 'expert_profiles_incomplete', "{$expertsNoSkills} Expertenprofil(e) ohne Skills — Matching findet sie nicht.", ['count' => $expertsNoSkills]);
        }

        $tendersNoApps = $count('tenders', fn ($q) => $q->where('status', 'open')->whereNotExists(fn ($sub) => $sub->selectRaw(1)->from('tender_applications')->whereColumn('tender_applications.tender_id', 'tenders.id')));
        if ($tendersNoApps) {
            $insights[] = $this->hit('info', 'tenders_no_applications', "{$tendersNoApps} Ausschreibung(en) ohne Bewerbung — Experten direkt ansprechen.", ['count' => $tendersNoApps]);
        }

        $strategiesEnding = $count('strategies', fn ($q) => $q->where('status', 'active')->whereBetween('ends_at', [now(), now()->addDays(7)]));
        if ($strategiesEnding) {
            $insights[] = $this->hit('info', 'strategies_ending_soon', "{$strategiesEnding} Strategie(n) — Enddatum in ≤7 Tagen.", ['count' => $strategiesEnding]);
        }

        $portfoliosNoInvestments = $count('portfolios', fn ($q) => $q->whereNotExists(fn ($sub) => $sub->selectRaw(1)->from('investments')->whereColumn('investments.portfolio_id', 'portfolios.id')->whereNull('disposed_at')));
        if ($portfoliosNoInvestments) {
            $insights[] = $this->hit('info', 'portfolios_no_investments', "{$portfoliosNoInvestments} Portfolio(s) ohne aktive Investitionen.", ['count' => $portfoliosNoInvestments]);
        }

        $strategiesNoProjects = $count('strategies', fn ($q) => $q->where('status', 'active')->whereNotExists(fn ($sub) => $sub->selectRaw(1)->from('projects')->whereColumn('projects.strategy_id', 'strategies.id')));
        if ($strategiesNoProjects) {
            $insights[] = $this->hit('info', 'strategies_no_projects', "{$strategiesNoProjects} aktive Strategie(n) ohne verknüpftes Projekt.", ['count' => $strategiesNoProjects]);
        }

        $projectsNoMeasures = $count('projects', fn ($q) => $q->whereIn('status', ['planned', 'active'])->whereNotExists(fn ($sub) => $sub->selectRaw(1)->from('measures')->whereColumn('measures.project_id', 'projects.id')));
        if ($projectsNoMeasures) {
            $insights[] = $this->hit('info', 'projects_no_measures', "{$projectsNoMeasures} Projekt(e) ohne Maßnahmen — Umsetzung fehlt.", ['count' => $projectsNoMeasures]);
        }

        $strategiesOverdue = $count('strategies', fn ($q) => $q->where('status', 'active')->where('ends_at', '<', now()));
        if ($strategiesOverdue) {
            $insights[] = $this->hit('info', 'strategies_overdue', "{$strategiesOverdue} aktive Strategie(n) über Enddatum — Status prüfen.", ['count' => $strategiesOverdue]);
        }

        $capitalNeed = DB::table('participations')->where('tenant_id', $t)
            ->where('status', 'active')->where('capital_need', '>', 0)->sum('capital_need');
        if ($capitalNeed > 0) {
            $insights[] = $this->hit('info', 'participations_capital_need', 'Gemeldeter Kapitalbedarf der Beteiligungen: '.number_format((float) $capitalNeed, 2, ',', '.').' €.', ['amount' => (float) $capitalNeed]);
        }

        $drawdown = DB::table('investments')->where('tenant_id', $t)
            ->whereNotNull('current_value')->whereNull('disposed_at')
            ->whereColumn('current_value', '<', 'cost_basis')->count();
        if ($drawdown) {
            $insights[] = $this->hit('warning', 'investments_drawdown', "{$drawdown} Investition(en) unter Einstandskurs — Bewertung prüfen.", ['count' => $drawdown]);
        }

        $graphOrphans = DB::table('graph_entities')->where('tenant_id', $t)
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('graph_edges')
                ->whereColumn('graph_edges.tenant_id', 'graph_entities.tenant_id')
                ->where(fn ($q2) => $q2->whereColumn('from_entity_id', 'graph_entities.id')->orWhereColumn('to_entity_id', 'graph_entities.id')))->count();
        if ($graphOrphans) {
            $insights[] = $this->hit('info', 'graph_orphans', "{$graphOrphans} Graph-Entität(en) ohne Verknüpfungen.", ['count' => $graphOrphans]);
        }

        $openQuestions = $count('questions', fn ($q) => $q->where('status', 'open'));
        if ($openQuestions) {
            $insights[] = $this->hit('info', 'questions_open', "{$openQuestions} offene Frage(n) im Expertennetzwerk.", ['count' => $openQuestions]);
        }

        $findingsOverdue = $count('audit_findings', fn ($q) => $q->whereIn('status', ['open', 'in_progress'])->where('due_at', '<', now()));
        if ($findingsOverdue) {
            $insights[] = $this->hit('warning', 'audit_findings_overdue', "{$findingsOverdue} Audit-Feststellung(en) überfällig.", ['count' => $findingsOverdue]);
        }

        $findingsSoon = $count('audit_findings', fn ($q) => $q->whereIn('status', ['open', 'in_progress'])->whereBetween('due_at', [now(), now()->addDays(7)]));
        if ($findingsSoon) {
            $insights[] = $this->hit('info', 'audit_findings_due_soon', "{$findingsSoon} Audit-Feststellung(en) innerhalb von 7 Tagen fällig.", ['count' => $findingsSoon]);
        }

        $findingsUnassigned = $count('audit_findings', fn ($q) => $q->whereIn('status', ['open', 'in_progress'])->whereNull('responsible_id'));
        if ($findingsUnassigned) {
            $insights[] = $this->hit('warning', 'audit_findings_unassigned', "{$findingsUnassigned} offene Audit-Feststellung(en) ohne Verantwortlichen.", ['count' => $findingsUnassigned]);
        }

        $findingsCritical = $count('audit_findings', fn ($q) => $q->whereIn('status', ['open', 'in_progress'])->whereIn('severity', ['high', 'critical']));
        if ($findingsCritical) {
            $insights[] = $this->hit('critical', 'audit_findings_critical', "{$findingsCritical} offene Audit-Feststellung(en) mit hoher/kritischer Schwere.", ['count' => $findingsCritical]);
        }

        $auditsSoon = $count('audits', fn ($q) => $q->where('status', 'planned')->whereBetween('starts_on', [now(), now()->addDays(7)]));
        if ($auditsSoon) {
            $insights[] = $this->hit('info', 'audits_starting_soon', "{$auditsSoon} Audit(s) starten innerhalb von 7 Tagen.", ['count' => $auditsSoon]);
        }

        $auditsOverdue = $count('audits', fn ($q) => $q->whereIn('status', ['planned', 'in_progress'])->where('ends_on', '<', now()));
        if ($auditsOverdue) {
            $insights[] = $this->hit('warning', 'audits_overdue', "{$auditsOverdue} Audit(s) über Enddatum hinaus offen.", ['count' => $auditsOverdue]);
        }

        $auditsUnassigned = $count('audits', fn ($q) => $q->whereIn('status', ['planned', 'in_progress'])->whereNull('responsible_id'));
        if ($auditsUnassigned) {
            $insights[] = $this->hit('warning', 'audits_unassigned', "{$auditsUnassigned} offene(s) Audit(s) ohne Verantwortlichen.", ['count' => $auditsUnassigned]);
        }

        $opDrafts = $count('operating_instructions', fn ($q) => $q->where('status', 'draft'));
        if ($opDrafts) {
            $insights[] = $this->hit('info', 'op_instructions_draft', "{$opDrafts} Betriebsanweisung(en) im Entwurfsstatus — prüfen und aktivieren.", ['count' => $opDrafts]);
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
