<?php

namespace Modules\DataPlatform\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Retention for the high-volume warehouse tables (stored_events,
 * metric_snapshots, anonymized_records). Events are the audit trail —
 * default retention is long; snapshots/twins are derived data and can
 * age out faster. All are chunked deletes so the daily run stays light.
 */
class PruneData extends Command
{
    protected $signature = 'data:prune
        {--events-days=730 : stored_events aelter als N Tage loeschen (Default 2 Jahre)}
        {--metrics-days=1095 : metric_snapshots aelter als N Tage loeschen (Default 3 Jahre)}
        {--twins-days=365 : anonymized_records aelter als N Tage loeschen}
        {--chunk=5000 : Rows pro Loesch-Batch}';

    protected $description = 'Retention: alte stored_events, metric_snapshots und anonymized_records loeschen.';

    public function handle(): int
    {
        $chunk = max(100, (int) $this->option('chunk'));

        $total = $this->prune('stored_events', 'created_at', (int) $this->option('events-days'), $chunk)
            + $this->prune('metric_snapshots', 'captured_on', (int) $this->option('metrics-days'), $chunk)
            + $this->prune('anonymized_records', 'synced_at', (int) $this->option('twins-days'), $chunk);

        $this->info("{$total} Zeile(n) geloescht.");

        return self::SUCCESS;
    }

    protected function prune(string $table, string $column, int $days, int $chunk): int
    {
        $days = max(1, $days);
        $cutoff = now()->subDays($days);
        $deleted = 0;
        do {
            $n = DB::table($table)->where($column, '<', $cutoff)->limit($chunk)->delete();
            $deleted += $n;
        } while ($n === $chunk);

        if ($deleted > 0) {
            $this->line("  {$table}: {$deleted} (> {$days} Tage)");
        }

        return $deleted;
    }
}
