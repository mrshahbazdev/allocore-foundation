<?php

namespace Modules\Tasks\Console;

use Illuminate\Console\Command;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Notifications\TaskDueSoon;

class RemindDueTasks extends Command
{
    protected $signature = 'tasks:remind {--hours=24 : Look-ahead window in hours}';

    protected $description = 'Sendet Erinnerungen fuer bald faellige Tasks an die zustaendigen Nutzer.';

    public function handle(): int
    {
        $tasks = Task::query()
            ->whereNotNull('assignee_id')
            ->whereNull('reminded_at')
            ->whereIn('status', [Task::STATUS_OPEN, Task::STATUS_IN_PROGRESS])
            ->whereNotNull('due_at')
            ->where('due_at', '<=', now()->addHours((int) $this->option('hours')))
            ->with('assignee')
            ->get();

        foreach ($tasks as $task) {
            $task->assignee?->notify(new TaskDueSoon($task));
            $task->update(['reminded_at' => now()]);
        }

        $this->info("{$tasks->count()} Erinnerung(en) versendet.");

        return self::SUCCESS;
    }
}
