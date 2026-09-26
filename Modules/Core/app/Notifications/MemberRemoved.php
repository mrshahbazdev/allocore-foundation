<?php

namespace Modules\Core\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MemberRemoved extends Notification
{
    use Queueable;

    public function __construct(public User $member) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'rollen',
            'id' => $this->member->id,
            'title' => 'Mitglied entfernt: '.$this->member->name.' ('.$this->member->email.')',
        ];
    }
}
