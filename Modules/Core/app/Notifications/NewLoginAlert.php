<?php

namespace Modules\Core\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewLoginAlert extends Notification
{
    use Queueable;

    public function __construct(public string $ip, public ?string $previousIp) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'anmeldung',
            'title' => 'Neue Anmeldung von anderer Adresse ('.$this->ip.', zuvor '.$this->previousIp.'). Falls Sie das nicht waren, ändern Sie sofort Ihr Passwort.',
        ];
    }
}
