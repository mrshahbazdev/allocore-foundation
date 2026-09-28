<?php

namespace Modules\Ai\Console;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Ai\Models\AiAnalysis;
use Modules\Ai\Notifications\AiCoachReport;
use Modules\Ai\Support\AiService;

class AiCoachCommand extends Command
{
    protected $signature = 'ai:coach {--tenant= : Nur diesen Mandanten bearbeiten}';

    protected $description = 'KI-Coach: wöchentliche Analyse je Mandant speichern + Admins benachrichtigen';

    public function handle(AiService $service): int
    {
        $tenantIds = $this->option('tenant')
            ? collect([(string) $this->option('tenant')])
            : DB::table('tenants')->pluck('id');

        $count = 0;
        foreach ($tenantIds as $tenantId) {
            $tenantId = (string) $tenantId;
            $tenant = Tenant::find($tenantId);
            if (! $tenant) {
                continue;
            }
            tenancy()->initialize($tenant);

            try {
                $result = $service->analyze();
                $status = 'completed';
            } catch (\Throwable $e) {
                $result = ['provider' => $service->provider()->name(), 'summary' => null, 'findings' => []];
                $status = 'failed';
                $this->warn("[{$tenantId}] Analyse fehlgeschlagen: {$e->getMessage()}");
            }

            $analysis = AiAnalysis::create([
                'tenant_id' => $tenantId,
                'kind' => 'coach',
                'provider' => $result['provider'],
                'status' => $status,
                'summary' => $result['summary'] ?? 'Analyse fehlgeschlagen',
                'findings' => $result['findings'],
            ]);

            if ($status !== 'completed') {
                continue;
            }

            $memberIds = DB::table('model_has_roles')
                ->where('team_id', $tenantId)
                ->where('model_type', User::class)
                ->pluck('model_id');
            $admins = User::whereIn('id', $memberIds)->get()
                ->filter(fn (User $u) => $u->hasPermissionTo('roles.manage'));

            $admins->each(fn (User $u) => $u->notify(
                new AiCoachReport($tenantId, $result['provider'], $result['summary'])
            ));

            $count++;
            $this->info("[{$tenantId}] {$result['provider']} → Analyse #{$analysis->id}");
        }

        $this->info("{$count} Coach-Analyse(n) erstellt.");

        return self::SUCCESS;
    }
}
