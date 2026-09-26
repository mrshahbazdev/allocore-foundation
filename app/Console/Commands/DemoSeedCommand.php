<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Modules\Audits\Models\Audit;
use Modules\Audits\Models\AuditFinding;
use Modules\Compliance\Models\Deadline;
use Modules\Compliance\Models\Inspection;
use Modules\Compliance\Models\Instruction;
use Modules\Compliance\Models\OperatingInstruction;
use Modules\Compliance\Models\RiskAssessment;
use Modules\Core\Models\Company;
use Modules\Core\Models\Person;
use Modules\CorporateDev\Models\Measure;
use Modules\CorporateDev\Models\Project;
use Modules\CorporateDev\Models\Strategy;
use Modules\ExpertNetwork\Models\ExpertProfile;
use Modules\ExpertNetwork\Models\Question;
use Modules\ExpertNetwork\Models\Tender;
use Modules\Finance\Models\FinancialReport;
use Modules\Hr\Models\LeaveRequest;
use Modules\Investments\Models\Investment;
use Modules\Investments\Models\Portfolio;
use Modules\Participations\Models\Participation;
use Modules\Production\Models\Machine;
use Modules\Production\Models\ProductionOrder;
use Modules\Tasks\Models\Task;

class DemoSeedCommand extends Command
{
    protected $signature = 'demo:seed {tenant : Tenant ID (e.g. from GET /api/v1/tenants)}';

    protected $description = 'Seed realistic demo data into a tenant across all modules (idempotent)';

    public function handle(): int
    {
        $tenant = Tenant::find($this->argument('tenant'));
        if (! $tenant) {
            $this->error('Tenant nicht gefunden.');

            return self::FAILURE;
        }

        tenancy()->initialize($tenant);

        $user = User::first();

        $company = Company::firstOrCreate(
            ['name' => 'DISAVO Zahntechnik GmbH'],
            ['legal_form' => 'GmbH', 'street' => 'Industriestr. 12', 'zip' => '10115', 'city' => 'Berlin', 'country' => 'DE']
        );
        $holding = Company::firstOrCreate(
            ['name' => 'DISAVO Holding GmbH'],
            ['legal_form' => 'GmbH', 'city' => 'Berlin', 'country' => 'DE']
        );

        $person = Person::firstOrCreate(
            ['email' => 'm.max@disavo.local'],
            ['company_id' => $company->id, 'first_name' => 'Max', 'last_name' => 'Mustermann', 'type' => 'employee']
        );
        $auditor = Person::firstOrCreate(
            ['email' => 'a.auditor@allocore.local'],
            ['company_id' => $holding->id, 'first_name' => 'Anna', 'last_name' => 'Auditor', 'type' => 'consultant']
        );

        Task::firstOrCreate(
            ['title' => 'Jahresbericht finalisieren'],
            ['status' => 'open', 'assignee_id' => $user?->id, 'due_at' => now()->addDays(5)]
        );
        Task::firstOrCreate(
            ['title' => 'Onboarding neuer Zahntechniker'],
            ['status' => 'in_progress', 'assignee_id' => $user?->id, 'due_at' => now()->addDays(12)]
        );

        Instruction::firstOrCreate(
            ['title' => 'Arbeitssicherheit Labortechnik'],
            ['content' => 'Jährliche Unterweisung Arbeitsplatzsicherheit.', 'person_id' => $person->id, 'status' => 'open', 'interval_months' => 12, 'due_at' => now()->addDays(20)]
        );
        Inspection::firstOrCreate(
            ['title' => 'Prüfung Fräsgeräte'],
            ['type' => 'Sicherheitsprüfung', 'subject' => 'Fräsmaschinen', 'responsible_id' => $user?->id, 'status' => 'open', 'scheduled_at' => now()->addDays(9)]
        );
        Deadline::firstOrCreate(
            ['title' => 'DGUV V3 Nachweis erneuern'],
            ['status' => 'open', 'due_at' => now()->addDays(14), 'responsible_id' => $user?->id]
        );
        RiskAssessment::firstOrCreate(
            ['title' => 'GB Zahntechnik-Labor'],
            ['area' => 'Produktion', 'hazard' => 'Staub/Chemikalien', 'risk_level' => 'medium', 'person_id' => $auditor->id, 'status' => 'open', 'review_at' => now()->addMonths(6)]
        );
        OperatingInstruction::firstOrCreate(
            ['title' => 'BA CAD/CAM Fräsmaschine'],
            ['content' => 'Bedien- und Sicherheitshinweise.', 'version' => '1.0', 'status' => 'active', 'valid_from' => now()->subMonths(3)]
        );

        $expert = ExpertProfile::firstOrCreate(
            ['person_id' => $person->id],
            ['headline' => 'CAD/CAM-Spezialist', 'bio' => '10 Jahre Zahntechnik.', 'skills' => ['cad_cam', 'ceramics', 'implantology'], 'hourly_rate' => 120, 'status' => 'active']
        );
        Question::firstOrCreate(
            ['title' => 'Empfehlung Sinterofen?'],
            ['body' => 'Welcher Sinterofen für Zirkon bei kleiner Serie?', 'category' => 'Technik', 'asked_by' => $user?->id, 'expert_profile_id' => $expert->id, 'status' => 'open']
        );
        Tender::firstOrCreate(
            ['title' => 'Ausschreibung Implantatversorgung Q4'],
            ['description' => '200 Einheiten, Krone/Abutment.', 'required_skills' => ['cad_cam', 'implantology'], 'budget' => 45000, 'company_id' => $company->id, 'status' => 'open', 'deadline_at' => now()->addDays(30)]
        );

        $strategy = Strategy::firstOrCreate(
            ['name' => 'Digitale Expansion 2026'],
            ['description' => 'Skalierung Zahntechnik + Plattform-Monetarisierung.', 'status' => 'active', 'starts_at' => now()->startOfYear(), 'ends_at' => now()->endOfYear()]
        );
        $project = Project::firstOrCreate(
            ['name' => 'ALLOCORE Go-Live'],
            ['strategy_id' => $strategy->id, 'description' => 'Rollout Plattform in allen Einheiten.', 'status' => 'active', 'progress' => 35, 'owner_id' => $user?->id, 'starts_at' => now()->subMonth(), 'ends_at' => now()->addMonths(5)]
        );
        Measure::firstOrCreate(
            ['title' => 'Pilotphase DISAVO Zahntechnik'],
            ['project_id' => $project->id, 'status' => 'active', 'responsible_id' => $user?->id, 'due_at' => now()->addMonth()]
        );

        $portfolio = Portfolio::firstOrCreate(
            ['name' => 'Kernportfolio'],
            ['type' => 'mixed', 'currency' => 'EUR']
        );
        Investment::firstOrCreate(
            ['name' => 'World ETF'],
            ['portfolio_id' => $portfolio->id, 'asset_class' => 'etf', 'quantity' => 120, 'cost_basis' => 9600, 'current_value' => 10850, 'valued_at' => now()->subDay(), 'acquired_at' => now()->subYear()]
        );
        Participation::firstOrCreate(
            ['name' => 'MediTech Nord GmbH'],
            ['company_id' => $holding->id, 'legal_form' => 'GmbH', 'stake_pct' => 30, 'invested_amount' => 150000, 'current_valuation' => 175000, 'status' => 'active', 'acquired_at' => now()->subYear()]
        );

        $machine = Machine::firstOrCreate(
            ['name' => 'Fräsmaschine M1'],
            ['type' => '5-Achs-CAD/CAM', 'capacity_units_per_day' => 40, 'status' => 'active']
        );
        ProductionOrder::firstOrCreate(
            ['order_no' => 'PO-2026-001'],
            ['product' => 'Zirkonkronen Serie A', 'quantity' => 120, 'machine_id' => $machine->id, 'assigned_to' => $person->id, 'status' => 'in_progress', 'scrap_qty' => 3, 'due_at' => now()->addDays(7)]
        );

        LeaveRequest::firstOrCreate(
            ['person_id' => $person->id, 'starts_on' => now()->addDays(30)->toDateString()],
            ['type' => 'vacation', 'ends_on' => now()->addDays(37)->toDateString(), 'status' => 'pending']
        );

        FinancialReport::firstOrCreate(
            ['company_id' => $company->id, 'period' => now()->format('Y-m')],
            ['revenue' => 210000, 'cashflow' => 42000, 'ebitda' => 38000, 'liquidity' => 135000]
        );

        $audit = Audit::firstOrCreate(
            ['title' => 'Internes Audit Arbeitssicherheit 2026'],
            ['type' => 'internal', 'standard' => 'ISO 45001', 'auditor' => 'Anna Auditor', 'company_id' => $company->id, 'responsible_id' => $user?->id, 'status' => 'planned', 'starts_on' => now()->addDays(10), 'ends_on' => now()->addDays(12)]
        );
        AuditFinding::firstOrCreate(
            ['audit_id' => $audit->id, 'title' => 'Fehlende Gefährdungsbeurteilung Lager'],
            ['description' => 'Für den Lagerbereich liegt keine aktuelle GB vor.', 'severity' => 'high', 'status' => 'open', 'due_at' => now()->addDays(21), 'responsible_id' => $user?->id]
        );
        AuditFinding::firstOrCreate(
            ['audit_id' => $audit->id, 'title' => 'Sicherheitsunterweisung Produktion rückständig'],
            ['description' => 'Jahresunterweisung Produktion ist nicht dokumentiert.', 'severity' => 'critical', 'status' => 'open', 'due_at' => now()->subDays(5), 'responsible_id' => $user?->id]
        );
        AuditFinding::firstOrCreate(
            ['audit_id' => $audit->id, 'title' => 'Notfallausrüstung prüfen'],
            ['description' => 'Augenspülstation im Labor monatlich prüfen.', 'severity' => 'medium', 'status' => 'in_progress', 'due_at' => now()->addDays(4), 'responsible_id' => $user?->id]
        );

        $this->call('analytics:aggregate');
        $this->info("Demo-Daten für Mandant {$tenant->name} angelegt.");

        return self::SUCCESS;
    }
}
