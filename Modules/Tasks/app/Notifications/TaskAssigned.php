<?php

namespace Modules\Tasks\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Modules\Tasks\Models\Task;

class TaskAssigned extends Notification
{
    use Queueable;

    public function __construct(public Task $task) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'aufgabe',
            'id' => $this->task->id,
            'title' => 'Aufgabe zugewiesen: '.$this->task->title,
            'due_at' => $this->task->due_at,
        ];
    }
}
