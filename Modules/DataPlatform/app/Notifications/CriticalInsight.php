<?php

namespace Modules\DataPlatform\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class CriticalInsight extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $tenantId,
        public string $code,
        public string $insightMessage,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'hinweis',
            'code' => $this->code,
            'title' => $this->insightMessage,
        ];
    }
}
