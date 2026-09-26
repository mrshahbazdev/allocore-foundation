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
            ->sortBy(fn ($i) => array_search($i['severity'], ['critical', 'warning', 'info']))
            ->values();
    }
}
