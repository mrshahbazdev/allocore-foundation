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
            ->subject($this->kindLabel().' faellig: '.($this->item->title ?? $this->item->name))
            ->line($this->kindLabel().' "'.($this->item->title ?? $this->item->name).'" ist am '.$this->dueAt.' faellig.');
    }

    private function kindLabel(): string
    {
        return [
            'unterweisung' => 'Unterweisung',
            'unterweisung_wiederholung' => 'Unterweisung-Wiederholung',
            'pruefung' => 'Pruefung',
            'frist' => 'Frist',
            'feststellung' => 'Audit-Feststellung',
            'massnahme' => 'Massnahme',
            'audit' => 'Audit',
            'gefaehrdungsbeurteilung' => 'Gefaehrdungsbeurteilung-Review',
            'projekt' => 'Projekt',
            'auftrag' => 'Produktionsauftrag',
            'ausschreibung' => 'Ausschreibung',
        ][$this->kind] ?? ucfirst($this->kind);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'id' => $this->item->id,
            'title' => $this->item->title ?? $this->item->name,
            'due_at' => $this->dueAt,
        ];
    }
}
