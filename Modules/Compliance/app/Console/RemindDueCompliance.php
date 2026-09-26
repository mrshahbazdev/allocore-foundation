<?php

namespace Modules\Compliance\Console;

use Illuminate\Console\Command;
use Modules\Audits\Models\AuditFinding;
use Modules\Compliance\Models\Deadline;
use Modules\Compliance\Models\Inspection;
use Modules\Compliance\Models\Instruction;
use Modules\Compliance\Notifications\ComplianceDueSoon;

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

        $this->info("{$count} Erinnerung(en) versendet.");

        return self::SUCCESS;
    }

    private function remind($query, string $kind, string $dateColumn = 'due_at'): int
    {
        $items = $query->whereNull('reminded_at')->whereNotNull('responsible_id')->with('responsible')->get();

        foreach ($items as $item) {
            $item->responsible?->notify(new ComplianceDueSoon($item, $kind, $item->{$dateColumn}->format('d.m.Y H:i')));
            $item->update(['reminded_at' => now()]);
        }

        return $items->count();
    }
}
