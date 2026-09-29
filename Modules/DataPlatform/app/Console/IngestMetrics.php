<?php

namespace Modules\DataPlatform\Console;

use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\DataPlatform\Models\MetricSnapshot;

/**
 * External-KPI ingest: maps known webhook event types (company tools pushing
 * to /api/v1/webhooks/{token}) onto atomic metric_snapshots per tenant.
 * Recomputes the full month window each run — idempotent and replayable.
 */
class IngestMetrics extends Command
{
    protected $signature = 'metrics:ingest {--date= : Snapshot date (default: today)}';

    protected $description = 'Bildet eingehende Webhook-Ereignisse auf atomare KPI-Snapshots ab (ext_* Metriken).';

    /** webhook.<sub> → [metric, op, field?]; op: sum (numeric field) or count. */
    private const MAP = [
        'invoice_created' => ['ext_revenue', 'sum', 'amount'],
        'invoice_paid' => ['ext_revenue_paid', 'sum', 'amount'],
        'expense_created' => ['ext_costs', 'sum', 'amount'],
        'payment_received' => ['ext_cash_in', 'sum', 'amount'],
        'expense_paid' => ['ext_cash_out', 'sum', 'amount'],
        'lead_created' => ['ext_leads', 'count'],
        'lead_qualified' => ['ext_mql', 'count'],
        'customer_created' => ['ext_new_customers', 'count'],
        'order_complaint' => ['ext_complaints', 'count'],
        'timeentry_billable' => ['ext_billable_hours', 'sum', 'hours'],
        'website_visitors' => ['ext_web_visitors', 'sum', 'visitors'],
    ];

    public function handle(): int
    {
        $date = $this->option('date') ?? today()->toDateString();
        $month = substr($date, 0, 7);
        $monthStart = $month.'-01 00:00:00';
        $monthEnd = Carbon::parse($monthStart)->addMonth()->toDateTimeString();
        $written = 0;

        foreach (Tenant::all() as $tenant) {
            $tenantKey = $tenant->getTenantKey();
            $metrics = [];

            $events = DB::table('stored_events')
                ->where('meta_data->tenant_id', $tenantKey)
                ->where('event_properties->type', 'like', 'webhook.%')
                ->where('created_at', '>=', $monthStart)
                ->where('created_at', '<', $monthEnd)
                ->get(['event_properties']);

            foreach ($events as $row) {
                $props = json_decode($row->event_properties, true) ?: [];
                $sub = substr((string) ($props['type'] ?? ''), strlen('webhook.'));
                $body = $props['payload']['body'] ?? [];

                if (isset(self::MAP[$sub])) {
                    [$metric, $op, $field] = array_pad(self::MAP[$sub], 3, null);
                    $metrics[$metric] = ($metrics[$metric] ?? 0) + ($op === 'count' ? 1 : (float) ($body[$field] ?? 0));
                }

                if ($sub === 'offer_created') {
                    $weighted = isset($body['probability'])
                        ? (float) ($body['value'] ?? 0) * ((float) $body['probability'] / 100)
                        : (float) ($body['value'] ?? 0);
                    $metrics['ext_pipeline_value'] = ($metrics['ext_pipeline_value'] ?? 0) + $weighted;
                }

                if ($sub === 'expense_created' && ($body['category'] ?? '') === 'marketing') {
                    $metrics['ext_costs_marketing'] = ($metrics['ext_costs_marketing'] ?? 0) + (float) ($body['amount'] ?? 0);
                }
            }

            foreach ($metrics as $metric => $value) {
                MetricSnapshot::withoutGlobalScopes()->updateOrCreate(
                    ['tenant_id' => $tenantKey, 'metric' => $metric, 'captured_on' => $date],
                    ['value' => round($value, 4)],
                );
                $written++;
            }
        }

        $this->info("{$written} ext_-Metriken geschrieben fuer {$date} (Monat {$month}).");

        return self::SUCCESS;
    }
}
