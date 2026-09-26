<?php

namespace Modules\Hr\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Modules\Hr\Models\LeaveRequest;

class LeaveDecided extends Notification
{
    use Queueable;

    public function __construct(public LeaveRequest $leave) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $status = $this->leave->status === 'approved' ? 'genehmigt' : 'abgelehnt';

        return [
            'kind' => 'urlaub',
            'id' => $this->leave->id,
            'title' => "Ihr Antrag wurde {$status} ({$this->leave->starts_on?->format('d.m.Y')} – {$this->leave->ends_on?->format('d.m.Y')})",
        ];
    }
}
