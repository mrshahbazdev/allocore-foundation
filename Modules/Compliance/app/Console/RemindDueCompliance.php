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
        );

        $count += $this->remind(
            Inspection::query()->where('status', Inspection::STATUS_SCHEDULED)->whereNotNull('scheduled_at')->where('scheduled_at', '<=', $horizon),
            'pruefung',
            'scheduled_at',
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

        $count += $this->remindRiskReviews($horizon);
        $count += $this->remindProductionOrders($horizon);
        $count += $this->remind(
            Tender::query()->where('status', Tender::STATUS_OPEN)->whereNotNull('deadline_at')->where('deadline_at', '<=', $horizon),
            'ausschreibung', 'deadline_at', 'created_by', 'creator');

        $this->info("{$count} Erinnerung(en) versendet.");

        return self::SUCCESS;
    }

    private function remind($query, string $kind, string $dateColumn = 'due_at', string $responsibleColumn = 'responsible_id', string $relation = 'responsible'): int
    {
        $items = $query->whereNull('reminded_at')->whereNotNull($responsibleColumn)->with($relation)->get();

        foreach ($items as $item) {
            $item->{$relation}?->notify(new ComplianceDueSoon($item, $kind, $item->{$dateColumn}->format('d.m.Y H:i')));
            $item->update(['reminded_at' => now()]);
        }

        return $items->count();
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
