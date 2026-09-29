<?php

namespace Modules\DataPlatform\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\DataPlatform\Support\KpiService;

class KpiController extends Controller
{
    /** Abgeleitete KPIs des Mandanten (read-seitig aus atomaren Snapshots). */
    public function index(Request $request)
    {
        $date = $request->query('date');

        return [
            'date' => $date ?? today()->toDateString(),
            'kpis' => KpiService::derived(tenant()->getTenantKey(), $date),
        ];
    }
}
