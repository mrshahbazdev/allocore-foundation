<?php

namespace Modules\Compliance\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ComplianceDueSoon extends Notification
{
    use Queueable;

    public function __construct(
        public Model $item,
        public string $kind,
        public string $dueAt,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Compliance-Frist faellig: '.$this->item->title)
            ->line(ucfirst($this->kind).' "'.$this->item->title.'" ist am '.$this->dueAt.' faellig.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'id' => $this->item->id,
            'title' => $this->item->title,
            'due_at' => $this->dueAt,
        ];
    }
}
