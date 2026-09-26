<?php

namespace Modules\ExpertNetwork\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Modules\ExpertNetwork\Models\TenderApplication;

class ApplicationDecided extends Notification
{
    use Queueable;

    public function __construct(public TenderApplication $application) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $status = [
            'awarded' => 'vergeben',
            'shortlisted' => 'vorgemerkt',
            'rejected' => 'abgelehnt',
        ][$this->application->status] ?? $this->application->status;

        return [
            'kind' => 'ausschreibung',
            'id' => $this->application->tender_id,
            'title' => 'Ihre Bewerbung auf „'.($this->application->tender?->title ?? 'Ausschreibung')."“ wurde {$status}",
        ];
    }
}
