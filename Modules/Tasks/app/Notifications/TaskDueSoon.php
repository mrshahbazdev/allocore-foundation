<?php

namespace Modules\Tasks\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Tasks\Models\Task;

class TaskDueSoon extends Notification
{
    use Queueable;

    public function __construct(public Task $task) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Aufgabe faellig: '.$this->task->title)
            ->line('Die Aufgabe "'.$this->task->title.'" ist am '.$this->task->due_at->format('d.m.Y H:i').' faellig.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'aufgabe',
            'id' => $this->task->id,
            'title' => $this->task->title,
            'due_at' => $this->task->due_at,
        ];
    }
}
