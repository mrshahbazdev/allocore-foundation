<?php

namespace Modules\DataPlatform\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\DataPlatform\Support\InsightService;

/**
 * KI-gestützte Steuerung (erste Ausbaustufe): regelbasierte Insights über
 * die operativen Tabellen des Tenants. Regeln sind austauschbar — später
 * kann dieselbe Schnittstelle von einem LLM-Service befüllt werden.
 */
class InsightController extends Controller
{
    public function index(Request $request)
    {
        return collect(app(InsightService::class)->collect(tenant()->getTenantKey()))
            ->filter()
            ->when($request->filled('severity'), fn ($c) => $c->filter(fn ($i) => $i['severity'] === $request->string('severity')->toString()))
            ->when($request->filled('code'), fn ($c) => $c->filter(fn ($i) => $i['code'] === $request->string('code')->toString()))
            ->when($request->filled('codes'), fn ($c) => $c->filter(fn ($i) => in_array($i['code'], array_filter(array_map('trim', explode(',', $request->string('codes')->toString()))), true)))
            ->when($request->filled('q'), fn ($c) => $c->filter(fn ($i) => str_contains(mb_strtolower($i['message']), mb_strtolower($request->string('q')->toString()))))
            ->sortBy(fn ($i) => array_search($i['severity'], ['critical', 'warning', 'info']))
            ->values();
    }

    public function stats(Request $request)
    {
        $insights = collect(app(InsightService::class)->collect(tenant()->getTenantKey()))
            ->filter()
            ->when($request->filled('severity'), fn ($c) => $c->filter(fn ($i) => $i['severity'] === $request->string('severity')->toString()))
            ->when($request->filled('code'), fn ($c) => $c->filter(fn ($i) => $i['code'] === $request->string('code')->toString()))
            ->when($request->filled('codes'), fn ($c) => $c->filter(fn ($i) => in_array($i['code'], array_filter(array_map('trim', explode(',', $request->string('codes')->toString()))), true)))
            ->when($request->filled('q'), fn ($c) => $c->filter(fn ($i) => str_contains(mb_strtolower($i['message']), mb_strtolower($request->string('q')->toString()))));

        return response()->json([
            'total' => $insights->count(),
            'by_severity' => $insights->countBy('severity'),
            'by_code' => $insights->countBy('code'),
            'by_code_group' => $insights->countBy(fn ($i) => explode('_', $i['code'])[0]),
        ]);
    }
}
