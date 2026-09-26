<?php

namespace Modules\Compliance\Console;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\Audits\Models\Audit;
use Modules\Audits\Models\AuditFinding;
use Modules\Compliance\Models\Deadline;
use Modules\Compliance\Models\Inspection;
use Modules\Compliance\Models\Instruction;
use Modules\Compliance\Models\RiskAssessment;
use Modules\Compliance\Notifications\ComplianceDueSoon;
use Modules\CorporateDev\Models\Measure;
use Modules\CorporateDev\Models\Project;
use Modules\ExpertNetwork\Models\Tender;
use Modules\Production\Models\ProductionOrder;

class RemindDueCompliance extends Command
{
    protected $signature = 'compliance:remind {--hours=24 : Look-ahead window in hours}';

    protected $description = 'Erinnert Verantwortliche an bald faellige Unterweisungen, Pruefungen und Fristen.';

    public function handle(): int
    {
        $horizon = now()->addHours((int) $this->option('hours'));
        $count = 0;

        $count += $this->remind(
            Instruction::query()->where('status', Instruction::STATUS_PENDING)->whereNotNull('due_at')->where('due_at', '<=', $horizon),
            'unterweisung',
            'due_at',
            'responsible_id',
            'responsible',
            'person_id',
            'person',
        );

        $count += $this->remind(
            Inspection::query()->where('status', Inspection::STATUS_SCHEDULED)->whereNotNull('scheduled_at')->where('scheduled_at', '<=', $horizon),
            'pruefung',
            'scheduled_at',
            'responsible_id',
            'responsible',
            'person_id',
            'person',
        );

        $count += $this->remind(
            Deadline::query()->where('status', Deadline::STATUS_OPEN)->where('due_at', '<=', $horizon),
            'frist',
        );

        $count += $this->remind(
            AuditFinding::query()->whereIn('status', ['open', 'in_progress'])->whereNotNull('due_at')->where('due_at', '<=', $horizon),
            'feststellung',
        );

        $count += $this->remind(
            Measure::query()->whereIn('status', ['open', 'in_progress'])->whereNotNull('due_at')->where('due_at', '<=', $horizon),
            'massnahme',
        );

        $count += $this->remind(
            Audit::query()->where('status', 'planned')->whereNotNull('starts_on')->where('starts_on', '<=', $horizon),
            'audit',
            'starts_on',
        );

        $count += $this->remind(
            Project::query()->whereIn('status', ['planned', 'active'])->whereNotNull('ends_at')->where('ends_at', '<=', $horizon),
            'projekt',
            'ends_at',
            'owner_id',
            'owner',
        );

        $count += $this->remindInstructionRenewals();

        $count += $this->remindRiskReviews($horizon);
        $count += $this->remindProductionOrders($horizon);
        $count += $this->remind(
            Tender::query()->where('status', Tender::STATUS_OPEN)->whereNotNull('deadline_at')->where('deadline_at', '<=', $horizon),
            'ausschreibung', 'deadline_at', 'created_by', 'creator');

        $this->info("{$count} Erinnerung(en) versendet.");

        return self::SUCCESS;
    }

    private function remind($query, string $kind, string $dateColumn = 'due_at', string $responsibleColumn = 'responsible_id', string $relation = 'responsible', ?string $personColumn = null, ?string $personRelation = null): int
    {
        $query->whereNull('reminded_at');
        if ($personColumn) {
            $items = (clone $query)->whereNotNull($responsibleColumn)->with([$relation, $personRelation])->get()
                ->merge((clone $query)->whereNull($responsibleColumn)->whereNotNull($personColumn)->with($personRelation)->get());
        } else {
            $items = $query->whereNotNull($responsibleColumn)->with($relation)->get();
        }

        $count = 0;
        foreach ($items as $item) {
            $user = $item->{$relation};
            if (! $user && $personRelation && $item->{$personRelation}?->email) {
                $user = User::where('email', $item->{$personRelation}->email)->first();
            }
            if (! $user) {
                continue;
            }
            $user->notify(new ComplianceDueSoon($item, $kind, $item->{$dateColumn}->format('d.m.Y H:i')));
            $item->update(['reminded_at' => now()]);
            $count++;
        }

        return $count;
    }

    private function remindInstructionRenewals(): int
    {
        $items = Instruction::query()
            ->where('status', 'completed')
            ->whereNotNull('interval_months')
            ->whereNotNull('completed_at')
            ->whereRaw('DATE_ADD(completed_at, INTERVAL interval_months MONTH) <= ?', [now()])
            ->whereNull('renewal_reminded_at')
            ->where(function ($q) {
                $q->whereNotNull('responsible_id')->orWhereNotNull('person_id');
            })
            ->with(['responsible', 'person'])
            ->get();

        $count = 0;
        foreach ($items as $item) {
            $user = $item->responsible;
            if (! $user && $item->person?->email) {
                $user = User::where('email', $item->person->email)->first();
            }
            if (! $user) {
                continue;
            }
            $due = $item->completed_at->copy()->addMonths((int) $item->interval_months);
            $user->notify(new ComplianceDueSoon($item, 'unterweisung_wiederholung', $due->format('d.m.Y H:i')));
            $item->update(['renewal_reminded_at' => now()]);
            $count++;
        }

        return $count;
    }

    private function remindRiskReviews(Carbon $horizon): int
    {
        $items = RiskAssessment::query()
            ->where('status', RiskAssessment::STATUS_OPEN)
            ->whereNotNull('review_at')
            ->where('review_at', '<=', $horizon)
            ->whereNull('reminded_at')
            ->whereNotNull('person_id')
            ->with('assessor')
            ->get();

        $count = 0;
        foreach ($items as $item) {
            $user = $item->assessor?->email ? User::where('email', $item->assessor->email)->first() : null;
            if (! $user) {
                continue;
            }
            $user->notify(new ComplianceDueSoon($item, 'gefaehrdungsbeurteilung', $item->review_at->format('d.m.Y H:i')));
            $item->update(['reminded_at' => now()]);
            $count++;
        }

        return $count;
    }

    private function remindProductionOrders(Carbon $horizon): int
    {
        $items = ProductionOrder::query()
            ->whereIn('status', ['queued', 'running'])
            ->whereNotNull('due_at')
            ->where('due_at', '<=', $horizon)
            ->whereNull('reminded_at')
            ->whereNotNull('assigned_to')
            ->with('assignee')
            ->get();

        $count = 0;
        foreach ($items as $item) {
            $user = $item->assignee?->email ? User::where('email', $item->assignee->email)->first() : null;
            if (! $user) {
                continue;
            }
            $user->notify(new ComplianceDueSoon($item, 'auftrag', $item->due_at->format('d.m.Y H:i')));
            $item->update(['reminded_at' => now()]);
            $count++;
        }

        return $count;
    }
}
