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
            ->when($request->subject_id, fn ($q) => $q->where('event_properties->subject->id', $request->subject_id))
            ->when($request->subject_type, fn ($q) => $q->where('event_properties->subject->type', $request->subject_type))
            ->when($request->action, fn ($q) => $q->where('event_properties->type', 'like', '%.'.$request->action))
            ->when($request->before_id, fn ($q) => $q->where('id', '<', $request->integer('before_id')))
            ->when($request->after_id, fn ($q) => $q->where('id', '>', $request->integer('after_id')))
            ->when($request->since, fn ($q) => $q->where('created_at', '>=', $request->date('since')))
            ->when($request->until, fn ($q) => $q->where('created_at', '<=', $request->date('until')))
            ->orderByDesc('id')
            ->paginate(min($request->integer('per_page', 50), 200));

        $page->through(function ($row) {
            $row->event_properties = json_decode($row->event_properties, true);
            $row->meta_data = json_decode($row->meta_data, true);

            return $row;
        });

        return $page;
    }
}
