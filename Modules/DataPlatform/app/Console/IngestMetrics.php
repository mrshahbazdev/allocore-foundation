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
 * Buckets events per month (payload occurred_at, fallback arrival); idempotent and replayable.
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
        'order_created' => ['ext_orders', 'count'],
        'order_done' => ['ext_orders_done', 'count'],
        'order_done_on_time' => ['ext_orders_on_time', 'count'],
        'order_lead_time' => ['ext_lead_time_days', 'sum', 'days'],
        'timeentry_billable' => ['ext_billable_hours', 'sum', 'hours'],
        'website_visitors' => ['ext_web_visitors', 'sum', 'visitors'],
    ];

    public function handle(): int
    {
        $date = $this->option('date') ?? today()->toDateString();
        $currentMonth = substr($date, 0, 7);
        $written = 0;

        foreach (Tenant::all() as $tenant) {
            $tenantKey = $tenant->getTenantKey();
            $metrics = [];

            $events = DB::table('stored_events')
                ->where('meta_data->tenant_id', $tenantKey)
                ->where('event_properties->type', 'like', 'webhook.%')
                ->get(['event_properties', 'created_at']);

            foreach ($events as $row) {
                $props = json_decode($row->event_properties, true) ?: [];
                $sub = substr((string) ($props['type'] ?? ''), strlen('webhook.'));
                $body = $props['payload']['body'] ?? [];

                $month = substr((string) $row->created_at, 0, 7);
                $occurred = (string) ($body['occurred_at'] ?? '');
                if (preg_match('/^\d{4}-\d{2}/', $occurred) === 1) {
                    $month = substr($occurred, 0, 7);
                }

                $bump = static function (string $metric, float $value) use (&$metrics, $month): void {
                    $metrics[$month][$metric] = ($metrics[$month][$metric] ?? 0) + $value;
                };

                if (isset(self::MAP[$sub])) {
                    [$metric, $op, $field] = array_pad(self::MAP[$sub], 3, null);
                    $bump($metric, $op === 'count' ? 1 : (float) ($body[$field] ?? 0));
                }

                if ($sub === 'offer_created') {
                    $weighted = isset($body['probability'])
                        ? (float) ($body['value'] ?? 0) * ((float) $body['probability'] / 100)
                        : (float) ($body['value'] ?? 0);
                    $bump('ext_pipeline_value', $weighted);
                }

                if ($sub === 'expense_created' && ($body['category'] ?? '') === 'marketing') {
                    $bump('ext_costs_marketing', (float) ($body['amount'] ?? 0));
                }
            }

            foreach ($metrics as $month => $values) {
                $capturedOn = $month === $currentMonth
                    ? $date
                    : Carbon::parse($month.'-01')->endOfMonth()->toDateString();

                foreach ($values as $metric => $value) {
                    MetricSnapshot::withoutGlobalScopes()->updateOrCreate(
                        ['tenant_id' => $tenantKey, 'metric' => $metric, 'captured_on' => $capturedOn],
                        ['value' => round($value, 4)],
                    );
                    $written++;
                }
            }
        }

        $this->info("{$written} ext_-Metriken geschrieben (Stichtag {$date}, je Monat bucketed).");

        return self::SUCCESS;
    }
}
