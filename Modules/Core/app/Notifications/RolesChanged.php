<?php

namespace Modules\Core\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RolesChanged extends Notification
{
    use Queueable;

    public function __construct(
        public User $member,
        public string $action,
        public array $roles = [],
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $title = match ($this->action) {
            'added' => 'Sie wurden zum Mandant hinzugefügt',
            'removed' => 'Sie wurden aus dem Mandant entfernt',
            default => 'Ihre Rollen wurden geändert'.($this->roles ? ': '.implode(', ', $this->roles) : ''),
        };

        return [
            'kind' => 'rollen',
            'id' => $this->member->id,
            'title' => $title,
        ];
    }
}
