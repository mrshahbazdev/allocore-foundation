<?php

namespace Modules\DataPlatform\Console;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\DataPlatform\Notifications\CriticalInsight;
use Modules\DataPlatform\Support\InsightService;

class NotifyCriticalInsights extends Command
{
    protected $signature = 'insights:notify {--warnings : Auch Warnungen verschicken (wöchentlicher Lauf)}';

    protected $description = 'Kritische Insights als Benachrichtigung an Mitglieder mit roles.manage';

    public function handle(InsightService $insights): int
    {
        // Dedupe-Keys älter als 30 Tage lösen sich auf — kehrt ein Hinweis zurück,
        // wird er erneut gemeldet statt für immer unterdrückt.
        DB::table('insight_notifications')
            ->where('created_at', '<', now()->subDays(30))
            ->delete();

        $tenantIds = DB::table('tenants')->pluck('id');

        foreach ($tenantIds as $tenantId) {
            $tenantId = (string) $tenantId;

            $memberIds = DB::table('model_has_roles')
                ->where('team_id', $tenantId)
                ->where('model_type', User::class)
                ->pluck('model_id');
            $admins = User::whereIn('id', $memberIds)->get()
                ->filter(fn (User $u) => $u->hasPermissionTo('roles.manage'));

            if ($admins->isEmpty()) {
                continue;
            }

            $severities = $this->option('warnings') ? ['critical', 'warning'] : ['critical'];

            foreach ($insights->collect($tenantId) as $insight) {
                if (! $insight || ! in_array($insight['severity'], $severities, true)) {
                    continue;
                }

                $key = sha1($insight['code'].'|'.$insight['message']);
                $seen = DB::table('insight_notifications')
                    ->where('tenant_id', $tenantId)
                    ->where('dedupe_key', $key)
                    ->exists();

                if ($seen) {
                    continue;
                }

                DB::table('insight_notifications')->insert([
                    'tenant_id' => $tenantId,
                    'dedupe_key' => $key,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $admins->each(fn (User $u) => $u->notify(
                    new CriticalInsight($tenantId, $insight['code'], $insight['message'])
                ));
            }
        }

        return self::SUCCESS;
    }
}
