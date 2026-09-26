<?php

namespace Modules\ExpertNetwork\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Modules\ExpertNetwork\Models\Answer;

class AnswerAccepted extends Notification
{
    use Queueable;

    public function __construct(public Answer $answer) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'antwort',
            'id' => $this->answer->question_id,
            'title' => 'Ihre Antwort wurde akzeptiert: '.($this->answer->question?->title ?? 'Frage'),
        ];
    }
}
