<?php

namespace Modules\DataPlatform\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\DataPlatform\Models\MetricSnapshot;

/**
 * Analytics layer: computes per-metric trends from the two most recent
 * warehouse snapshots — direction and delta for dashboards (Layer 7).
 */
class AnalyticsController extends Controller
{
    public function trends(Request $request)
    {
        $metrics = MetricSnapshot::query()
            ->where('tenant_id', tenant()->getTenantKey())
            ->when($request->keys, fn ($q) => $q->whereIn('metric', array_filter(array_map('trim', explode(',', $request->keys)))))
            ->when($request->q, fn ($q) => $q->where('metric', 'like', '%'.$request->q.'%'))
            ->distinct()->pluck('metric');

        return $metrics->map(function (string $metric) {
            [$curr, $prev] = MetricSnapshot::query()
                ->where('tenant_id', tenant()->getTenantKey())
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
        })->when($request->direction, function ($c) use ($request) {
            return $c->where('direction', $request->direction);
        })->sortBy('metric')->values();
    }
}
