<?php

namespace Modules\DataPlatform\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\DataPlatform\Models\MetricSnapshot;

/**
 * Analytics layer: computes per-metric trends from the two most recent
 * warehouse snapshots — direction and delta for dashboards (Layer 7).
 */
class AnalyticsController extends Controller
{
    public function trends()
    {
        $metrics = MetricSnapshot::query()->distinct()->pluck('metric');

        return $metrics->map(function (string $metric) {
            [$curr, $prev] = MetricSnapshot::query()
                ->where('metric', $metric)
                ->orderByDesc('id')
                ->limit(2)
                ->get(['value', 'captured_on'])
                ->pad(2, null);

            $delta = $prev ? round($curr->value - $prev->value, 2) : null;

            return [
                'metric' => $metric,
                'value' => $curr->value,
                'captured_on' => $curr->captured_on,
                'previous' => $prev?->value,
                'previous_on' => $prev?->captured_on,
                'delta' => $delta,
                'direction' => $delta === null ? 'unknown' : ($delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat')),
            ];
        })->sortBy('metric')->values();
    }
}
