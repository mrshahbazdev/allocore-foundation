<?php

namespace Modules\DataPlatform\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\DataPlatform\Events\DomainEvent;
use Modules\DataPlatform\Models\IntegrationSource;

class StoreWebhookEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $sourceId,
        public string $kind,
        public array $subject,
        public array $body,
    ) {}

    public function handle(): void
    {
        $source = IntegrationSource::query()->where('id', $this->sourceId)->where('active', true)->first();
        if (! $source) {
            return;
        }

        $tenantKey = $source->tenant_id;

        $event = new DomainEvent(
            type: 'webhook.'.$this->kind,
            tenantId: $tenantKey,
            subject: $this->subject ?: ['type' => 'integration_source', 'id' => (string) $source->id, 'title' => $source->name],
            payload: [
                'source_id' => $source->id,
                'source_name' => $source->name,
                'body' => $this->body,
            ],
        );
        $event->setMetaData(['tenant_id' => $tenantKey]);
        event($event);

        $source->update(['last_received_at' => now()]);
    }
}
