<?php

namespace Modules\ExpertNetwork\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Modules\ExpertNetwork\Models\Question;

class QuestionPublished extends Notification
{
    use Queueable;

    public function __construct(public Question $question) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'frage',
            'id' => $this->question->id,
            'title' => 'Neue Frage: '.($this->question->title ?? 'Unbenannt'),
        ];
    }
}
