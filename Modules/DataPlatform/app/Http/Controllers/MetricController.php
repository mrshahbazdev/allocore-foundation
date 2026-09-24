<?php

namespace Modules\DataPlatform\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\DataPlatform\Models\MetricSnapshot;

class MetricController extends Controller
{
    /** Latest value of every metric for the current tenant. */
    public function index()
    {
        return MetricSnapshot::query()
            ->select('metric', 'value', 'captured_on')
            ->whereIn('id', function ($q) {
                $q->selectRaw('MAX(id)')->from('metric_snapshots')->groupBy('metric');
            })
            ->orderBy('metric')
            ->get()
            ->keyBy('metric');
    }

    /** History of one metric. */
    public function show(Request $request, string $metric)
    {
        return MetricSnapshot::query()
            ->where('metric', $metric)
            ->when($request->from, fn ($q) => $q->where('captured_on', '>=', $request->from))
            ->when($request->to, fn ($q) => $q->where('captured_on', '<=', $request->to))
            ->orderBy('captured_on')
            ->get(['metric', 'value', 'captured_on']);
    }
}
