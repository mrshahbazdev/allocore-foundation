<?php

namespace Modules\ExpertNetwork\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Modules\ExpertNetwork\Models\Answer;

class QuestionAnswered extends Notification
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
            'title' => 'Neue Antwort auf Ihre Frage: '.($this->answer->question?->title ?? 'Frage'),
        ];
    }
}
