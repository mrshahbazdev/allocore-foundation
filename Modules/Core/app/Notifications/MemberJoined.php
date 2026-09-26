<?php

namespace Modules\Core\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MemberJoined extends Notification
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
            'title' => 'Neues Mitglied: '.$this->member->name.' ('.$this->member->email.')',
        ];
    }
}
