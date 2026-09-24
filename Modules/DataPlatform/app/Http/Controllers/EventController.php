<?php

namespace Modules\DataPlatform\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $page = DB::table('stored_events')
            ->where('meta_data->tenant_id', tenant()->getTenantKey())
            ->when($request->type, fn ($q) => $q->where('event_properties->type', $request->type))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 50));

        $page->through(function ($row) {
            $row->event_properties = json_decode($row->event_properties, true);
            $row->meta_data = json_decode($row->meta_data, true);

            return $row;
        });

        return $page;
    }
}
