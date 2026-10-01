<?php

namespace Modules\Ai\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AiCoachReport extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $tenantId,
        public string $provider,
        public string $summary,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'ki_coach',
            'code' => 'ai_coach_weekly',
            'title' => 'KI-Coach: '.$this->summary,
        ];
    }
}
