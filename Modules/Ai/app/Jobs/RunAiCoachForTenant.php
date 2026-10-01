<?php

namespace Modules\Ai\Jobs;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Ai\Models\AiAnalysis;
use Modules\Ai\Notifications\AiCoachReport;
use Modules\Ai\Support\AiService;

class RunAiCoachForTenant implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(public string $tenantId) {}

    public function handle(AiService $service): void
    {
        $tenant = Tenant::find($this->tenantId);
        if (! $tenant) {
            return;
        }
        tenancy()->initialize($tenant);

        try {
            $result = $service->analyze();
            $status = 'completed';
        } catch (\Throwable $e) {
            $result = ['provider' => $service->provider()->name(), 'summary' => null, 'findings' => []];
            $status = 'failed';
            Log::warning('ai:coach Analyse fehlgeschlagen', ['tenant' => $this->tenantId, 'error' => $e->getMessage()]);
        }

        $analysis = AiAnalysis::create([
            'tenant_id' => $this->tenantId,
            'kind' => 'coach',
            'provider' => $result['provider'],
            'status' => $status,
            'summary' => $result['summary'] ?? 'Analyse fehlgeschlagen',
            'findings' => $result['findings'],
        ]);

        if ($status !== 'completed') {
            return;
        }

        $memberIds = DB::table('model_has_roles')
            ->where('team_id', $this->tenantId)
            ->where('model_type', User::class)
            ->pluck('model_id');
        $admins = User::whereIn('id', $memberIds)->get()
            ->filter(fn (User $u) => $u->hasPermissionTo('roles.manage'));

        $admins->each(fn (User $u) => $u->notify(
            new AiCoachReport($this->tenantId, $result['provider'], $result['summary'])
        ));
    }
}
