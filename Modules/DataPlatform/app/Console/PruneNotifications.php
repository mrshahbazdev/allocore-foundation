<?php

namespace Modules\DataPlatform\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneNotifications extends Command
{
    protected $signature = 'notifications:prune {--days=90 : Gelesene Benachrichtigungen aelter als N Tage loeschen}';

    protected $description = 'Loescht gelesene Benachrichtigungen aelter als N Tage (Retention).';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));

        $deleted = DB::table('notifications')
            ->whereNotNull('read_at')
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        $keysDeleted = DB::table('insight_notifications')
            ->where('created_at', '<', now()->subDays(60))
            ->delete();

        $this->info("{$deleted} Benachrichtigung(en) geloescht (gelesen, > {$days} Tage), {$keysDeleted} Insight-Dedupe-Key(s) (> 60 Tage).");

        return self::SUCCESS;
    }
}
