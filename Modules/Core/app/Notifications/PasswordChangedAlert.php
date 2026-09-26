<?php

namespace Modules\Core\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PasswordChangedAlert extends Notification
{
    use Queueable;

    public function __construct(public string $via) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'passwort_geaendert',
            'title' => 'Ihr Passwort wurde geändert ('.$this->via.'). Falls Sie das nicht waren, kontaktieren Sie sofort einen Administrator.',
        ];
    }
}
