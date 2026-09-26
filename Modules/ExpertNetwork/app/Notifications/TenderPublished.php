<?php

namespace Modules\ExpertNetwork\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Modules\ExpertNetwork\Models\Tender;

class TenderPublished extends Notification
{
    use Queueable;

    public function __construct(public Tender $tender) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'ausschreibung',
            'id' => $this->tender->id,
            'title' => 'Neue Ausschreibung: '.($this->tender->title ?? 'Unbenannt'),
            'due_at' => $this->tender->deadline_at?->format('d.m.Y'),
        ];
    }
}
