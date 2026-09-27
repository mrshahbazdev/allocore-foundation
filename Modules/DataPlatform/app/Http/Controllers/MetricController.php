<?php

namespace Modules\DataPlatform\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\DataPlatform\Models\MetricSnapshot;

class MetricController extends Controller
{
    /** Latest value of every metric for the current tenant. */
    public function index(Request $request)
    {
        return MetricSnapshot::query()
            ->select('metric', 'value', 'captured_on')
            ->where('tenant_id', tenant()->getTenantKey())
            ->when($request->keys, fn ($q) => $q->whereIn('metric', array_filter(array_map('trim', explode(',', $request->keys)))))
            ->whereIn('id', function ($q) {
                $q->selectRaw('MAX(id)')->from('metric_snapshots')->groupBy('metric', 'tenant_id');
            })
            ->orderBy('metric')
            ->get()
            ->keyBy('metric');
    }

    /** History of one metric. */
    public function show(Request $request, string $metric)
    {
        return MetricSnapshot::query()
            ->where('tenant_id', tenant()->getTenantKey())
            ->where('metric', $metric)
            ->when($request->from, fn ($q) => $q->where('captured_on', '>=', $request->from))
            ->when($request->to, fn ($q) => $q->where('captured_on', '<=', $request->to))
            ->orderBy('captured_on', $request->string('dir')->toString() === 'desc' ? 'desc' : 'asc')
            ->limit(min($request->integer('limit', 1000), 5000))
            ->get(['metric', 'value', 'captured_on']);
    }
}
