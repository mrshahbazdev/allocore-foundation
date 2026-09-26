<?php

namespace Modules\Core\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class FailedLoginAlert extends Notification
{
    use Queueable;

    public function __construct(public string $ip) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'anmeldeversuche',
            'title' => 'Mehrere fehlgeschlagene Anmeldeversuche fuer Ihr Konto (IP '.$this->ip.'). Falls Sie das nicht waren, ändern Sie Ihr Passwort.',
        ];
    }
}
