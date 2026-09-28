<?php

namespace Modules\DataPlatform\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Modules\DataLake\Models\DataObject;
use Modules\DataPlatform\Events\DomainEvent;
use Modules\DataPlatform\Models\IntegrationConnector;

class PullConnectorsCommand extends Command
{
    protected $signature = 'integrations:pull {--connector=} {--tenant=}';

    protected $description = 'Pull-Konnektoren ausführen: URL abrufen, Antwort als Data-Lake-Objekt + Event speichern';

    public function handle(): int
    {
        $query = IntegrationConnector::query()->where('active', true)
            ->when($this->option('connector'), fn ($q) => $q->where('id', $this->option('connector')))
            ->when($this->option('tenant'), fn ($q) => $q->where('tenant_id', $this->option('tenant')));

        $count = 0;
        foreach ($query->get() as $connector) {
            if (! $connector->due() && ! $this->option('connector')) {
                continue;
            }
            $count++;
            $this->runConnector($connector);
        }

        $this->info("{$count} Konnektor(en) ausgeführt.");

        return self::SUCCESS;
    }

    private function runConnector(IntegrationConnector $connector): void
    {
        $tenantKey = (string) $connector->tenant_id;

        try {
            $response = Http::withHeaders($connector->headers ?? [])->timeout(30)->get($connector->url);
            $status = 'http_'.$response->status();
            $body = $response->body();
        } catch (\Throwable $e) {
            $connector->update(['last_run_at' => now(), 'last_status' => 'error: '.substr($e->getMessage(), 0, 120)]);
            $this->warn("[{$connector->name}] fehlgeschlagen: {$e->getMessage()}");

            return;
        }

        $path = "connectors/{$connector->id}/".date('Ymd_His').'.json';
        Storage::disk('local')->put($path, $body);

        $object = new DataObject;
        $object->forceFill([
            'tenant_id' => $tenantKey,
            'name' => $connector->name.' — '.now()->format('d.m.Y H:i'),
            'category' => 'other',
            'mime_type' => str_contains($response->header('Content-Type') ?? '', 'json') ? 'application/json' : 'text/plain',
            'size_bytes' => strlen($body),
            'disk' => 'local',
            'path' => $path,
        ]);
        $object->save();

        $connector->update(['last_run_at' => now(), 'last_status' => $status]);

        $event = new DomainEvent(
            type: 'connector.pulled',
            tenantId: $tenantKey,
            subject: ['type' => 'integration_connector', 'id' => (string) $connector->id, 'title' => $connector->name],
            payload: ['url' => $connector->url, 'status' => $status, 'bytes' => strlen($body)],
        );
        $event->setMetaData(['tenant_id' => $tenantKey]);
        event($event);

        $this->info("[{$connector->name}] {$status}, ".strlen($body).' B');
    }
}
