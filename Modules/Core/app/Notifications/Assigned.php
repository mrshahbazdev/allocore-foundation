<?php

namespace Modules\Core\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class Assigned extends Notification
{
    use Queueable;

    public function __construct(
        public string $kind,
        public string $entityId,
        public string $title,
        public ?string $dueAt = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'id' => $this->entityId,
            'title' => $this->title,
            'due_at' => $this->dueAt,
        ];
    }
}
