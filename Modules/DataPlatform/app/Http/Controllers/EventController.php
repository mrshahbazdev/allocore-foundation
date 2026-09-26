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

    /** Audit-export: alle Events des Mandanten als NDJSON-Stream (gleiche Filter wie index). */
    public function export(Request $request)
    {
        $tenantKey = tenant()->getTenantKey();

        $query = DB::table('stored_events')
            ->where('meta_data->tenant_id', $tenantKey)
            ->when($request->type, fn ($q) => $q->where('event_properties->type', $request->type))
            ->when($request->subject_id, fn ($q) => $q->where('event_properties->subject->id', $request->subject_id))
            ->when($request->subject_type, fn ($q) => $q->where('event_properties->subject->type', $request->subject_type))
            ->when($request->action, fn ($q) => $q->where('event_properties->type', 'like', '%.'.$request->action))
            ->when($request->group, fn ($q) => $q->where('event_properties->type', 'like', $request->group.'.%'))
            ->when($request->since, fn ($q) => $q->where('created_at', '>=', $request->date('since')))
            ->when($request->until, fn ($q) => $q->where('created_at', '<=', $request->date('until')))
            ->orderBy('id')
            ->select('id', 'event_class', 'event_properties', 'meta_data', 'created_at');

        return response()->stream(function () use ($query) {
            $query->chunk(500, function ($rows) {
                foreach ($rows as $row) {
                    $row->event_properties = json_decode($row->event_properties, true);
                    $row->meta_data = json_decode($row->meta_data, true);
                    echo json_encode($row, JSON_UNESCAPED_UNICODE), "\n";
                }
            });
        }, 200, [
            'Content-Type' => 'application/x-ndjson; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="allocore-events-'.now()->format('Ymd-His').'.ndjson"',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
