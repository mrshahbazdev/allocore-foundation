<?php

namespace Modules\DataPlatform\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\DataPlatform\Events\DomainEvent;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $page = DB::table('stored_events')
            ->where('meta_data->tenant_id', tenant()->getTenantKey())
            ->when($request->id, fn ($q) => $q->where('id', $request->integer('id')))
            ->when($request->type, fn ($q) => $q->where('event_properties->type', $request->type))
            ->when($request->subject_id, fn ($q) => $q->where('event_properties->subject->id', $request->subject_id))
            ->when($request->subject_type, fn ($q) => $q->where('event_properties->subject->type', $request->subject_type))
            ->when($request->action, fn ($q) => $q->where('event_properties->type', 'like', '%.'.$request->action))
            ->when($request->actions, function ($q) use ($request) {
                $q->where(function ($w) use ($request) {
                    foreach (array_filter(array_map('trim', explode(',', $request->actions))) as $a) {
                        $w->orWhere('event_properties->type', 'like', '%.'.$a);
                    }
                });
            })
            ->when($request->group, fn ($q) => $q->where('event_properties->type', 'like', $request->group.'.%'))
            ->when($request->groups, function ($q) use ($request) {
                $q->where(function ($w) use ($request) {
                    foreach (array_filter(array_map('trim', explode(',', $request->groups))) as $g) {
                        $w->orWhere('event_properties->type', 'like', $g.'.%');
                    }
                });
            })
            ->when($request->q, fn ($q) => $q->where('event_properties->subject->title', 'like', '%'.$request->q.'%'))
            ->when($request->actor, fn ($q) => $q->where('meta_data->actor->id', $request->actor === 'me' ? $request->user()->id : $request->integer('actor')))
            ->when($request->actors, fn ($q) => $q->whereIn('meta_data->actor->id', array_map(fn ($a) => $a === 'me' ? $request->user()->id : (int) $a, array_filter(array_map('trim', explode(',', $request->actors))))))
            ->when($request->actor_name, fn ($q) => $q->where('meta_data->actor->name', 'like', '%'.$request->actor_name.'%'))
            ->when($request->types, fn ($q) => $q->whereIn('event_properties->type', array_filter(array_map('trim', explode(',', $request->types)))))
            ->when($request->before_id, fn ($q) => $q->where('id', '<', $request->integer('before_id')))
            ->when($request->after_id, fn ($q) => $q->where('id', '>', $request->integer('after_id')))
            ->when($request->since, fn ($q) => $q->where('created_at', '>=', $request->date('since')))
            ->when($request->until, fn ($q) => $q->where('created_at', '<=', $request->date('until')))
            ->when($request->day, fn ($q) => $q->whereDate('created_at', $request->date('day')))
            ->orderBy('id', $request->string('dir')->toString() === 'asc' ? 'asc' : 'desc')
            ->paginate(min($request->integer('per_page', 50), 200));

        $page->through(function ($row) {
            $row->event_properties = json_decode($row->event_properties, true);
            $row->meta_data = json_decode($row->meta_data, true);

            return $row;
        });

        return $page;
    }

    /** Einzelnes Ereignis des aktuellen Mandanten lesen. */
    public function show(int $event)
    {
        $row = DB::table('stored_events')
            ->where('meta_data->tenant_id', tenant()->getTenantKey())
            ->where('id', $event)
            ->first();

        abort_unless($row, 404);

        return response()->json($row);
    }

    /** Manuelles Event in den Mandanten-Event-Store schreiben (z. B. externe Integrationen). */
    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|string|max:255|regex:/^[a-z0-9_]+\.[a-z0-9_]+$/',
            'subject' => 'required|array',
            'subject.type' => 'required|string|max:100',
            'subject.id' => 'nullable|string|max:100',
            'subject.title' => 'nullable|string|max:500',
            'payload' => 'nullable|array',
        ]);

        $tenantKey = (string) tenant()->getTenantKey();
        $event = new DomainEvent(
            type: $data['type'],
            tenantId: $tenantKey,
            subject: $data['subject'],
            payload: $data['payload'] ?? [],
        );
        $event->setMetaData(['tenant_id' => $tenantKey] + DomainEvent::actorMeta());
        event($event);

        return response()->json(['status' => 'recorded', 'id' => $event->storedEventId() ?? null], 201);
    }

    /** Aggregierte Übersicht: total, by_group (Präfix vor dem ersten Punkt), per_day letzte 7 Tage. */
    public function summary(Request $request)
    {
        $base = DB::table('stored_events')
            ->where('meta_data->tenant_id', tenant()->getTenantKey())
            ->when($request->since, fn ($q) => $q->where('created_at', '>=', $request->date('since')))
            ->when($request->until, fn ($q) => $q->where('created_at', '<=', $request->date('until')))
            ->when($request->day, fn ($q) => $q->whereDate('created_at', $request->date('day')))
            ->when($request->group, fn ($q) => $q->where('event_properties->type', 'like', $request->group.'.%'))
            ->when($request->groups, function ($q) use ($request) {
                $q->where(function ($w) use ($request) {
                    foreach (array_filter(array_map('trim', explode(',', $request->groups))) as $g) {
                        $w->orWhere('event_properties->type', 'like', $g.'.%');
                    }
                });
            })
            ->when($request->type, fn ($q) => $q->where('event_properties->type', $request->type))
            ->when($request->subject_id, fn ($q) => $q->where('event_properties->subject->id', $request->subject_id))
            ->when($request->subject_type, fn ($q) => $q->where('event_properties->subject->type', $request->subject_type))
            ->when($request->action, fn ($q) => $q->where('event_properties->type', 'like', '%.'.$request->action))
            ->when($request->actions, function ($q) use ($request) {
                $q->where(function ($w) use ($request) {
                    foreach (array_filter(array_map('trim', explode(',', $request->actions))) as $a) {
                        $w->orWhere('event_properties->type', 'like', '%.'.$a);
                    }
                });
            })
            ->when($request->q, fn ($q) => $q->where('event_properties->subject->title', 'like', '%'.$request->q.'%'))
            ->when($request->actor, fn ($q) => $q->where('meta_data->actor->id', $request->actor === 'me' ? $request->user()->id : $request->integer('actor')))
            ->when($request->actors, fn ($q) => $q->whereIn('meta_data->actor->id', array_map(fn ($a) => $a === 'me' ? $request->user()->id : (int) $a, array_filter(array_map('trim', explode(',', $request->actors))))))
            ->when($request->actor_name, fn ($q) => $q->where('meta_data->actor->name', 'like', '%'.$request->actor_name.'%'))
            ->when($request->types, fn ($q) => $q->whereIn('event_properties->type', array_filter(array_map('trim', explode(',', $request->types)))));

        $byGroup = (clone $base)
            ->selectRaw("SUBSTRING_INDEX(JSON_UNQUOTE(JSON_EXTRACT(`event_properties`, '$.type')), '.', 1) as grp, COUNT(*) as n")
            ->groupBy('grp')->orderByDesc('n')->pluck('n', 'grp');

        $byAction = (clone $base)
            ->selectRaw("SUBSTRING_INDEX(JSON_UNQUOTE(JSON_EXTRACT(`event_properties`, '$.type')), '.', -1) as act, COUNT(*) as n")
            ->groupBy('act')->orderByDesc('n')->pluck('n', 'act');
        $byType = (clone $base)
            ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(`event_properties`, '$.type')) as etype, COUNT(*) as n")
            ->groupBy('etype')->orderByDesc('n')->limit(50)->pluck('n', 'etype');

        $byActor = (clone $base)
            ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(`meta_data`, '$.actor.name')) as who, COUNT(*) as n")
            ->groupBy('who')->orderByDesc('n')->pluck('n', 'who');

        $topActors = (clone $base)
            ->whereNotNull(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(`meta_data`, '$.actor.id'))"))
            ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(`meta_data`, '$.actor.id')) as aid, JSON_UNQUOTE(JSON_EXTRACT(`meta_data`, '$.actor.name')) as aname, COUNT(*) as n")
            ->groupBy('aid', 'aname')->orderByDesc('n')->limit(min($request->integer('top', 5), 25))->get()
            ->map(fn ($r) => ['id' => $r->aid, 'name' => $r->aname, 'events' => (int) $r->n]);

        $topSubjects = (clone $base)
            ->whereNotNull(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(`event_properties`, '$.subject.id'))"))
            ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(`event_properties`, '$.subject.id')) as sid, JSON_UNQUOTE(JSON_EXTRACT(`event_properties`, '$.subject.type')) as stype, JSON_UNQUOTE(JSON_EXTRACT(`event_properties`, '$.subject.title')) as stitle, COUNT(*) as n")
            ->groupBy('sid', 'stype', 'stitle')->orderByDesc('n')->limit(min($request->integer('top', 5), 25))->get()
            ->map(fn ($r) => ['id' => $r->sid, 'type' => $r->stype, 'title' => $r->stitle, 'events' => (int) $r->n]);

        $days = min($request->integer('days', 7), 90);
        $perDay = (clone $base)->where('created_at', '>=', now()->subDays($days)->startOfDay())
            ->selectRaw('DATE(created_at) as day, COUNT(*) as n')
            ->groupBy('day')->pluck('n', 'day');

        $byWeekday = (clone $base)
            ->selectRaw('DAYOFWEEK(created_at) as dow, COUNT(*) as n')
            ->groupBy('dow')->pluck('n', 'dow');

        $byHour = (clone $base)
            ->selectRaw('HOUR(created_at) as h, COUNT(*) as n')
            ->groupBy('h')->pluck('n', 'h');

        return response()->json([
            'total' => (clone $base)->count(),
            'first_event_at' => (clone $base)->min('created_at'),
            'last_event_at' => (clone $base)->max('created_at'),
            'by_group' => $byGroup,
            'by_action' => $byAction,
            'by_type' => $byType,
            'by_actor' => $byActor,
            'top_actors' => $topActors,
            'top_subjects' => $topSubjects,
            'per_day' => $perDay,
            'by_weekday' => $byWeekday,
            'by_hour' => $byHour,
            'avg_per_day' => round($perDay->avg(), 1),
            'busiest_day' => ($top = $perDay->sortDesc()->keys()->first()) ? ['date' => $top, 'events' => $perDay[$top]] : null,
        ]);
    }

    /** Distinct Auslöser (meta_data.actor) des Mandanten — id, name, Anzahl Events. */
    public function actors(Request $request)
    {
        $rows = DB::table('stored_events')
            ->where('meta_data->tenant_id', tenant()->getTenantKey())
            ->when($request->q, fn ($q) => $q->where('meta_data->actor->name', 'like', '%'.$request->q.'%'))
            ->whereNotNull(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(`meta_data`, '$.actor.id'))"))
            ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(`meta_data`, '$.actor.id')) as actor_id, JSON_UNQUOTE(JSON_EXTRACT(`meta_data`, '$.actor.name')) as actor_name, COUNT(*) as events")
            ->groupBy('actor_id', 'actor_name')
            ->orderByDesc('events')
            ->get()
            ->map(fn ($r) => ['id' => (int) $r->actor_id, 'name' => $r->actor_name, 'events' => (int) $r->events]);

        return response()->json(['data' => $rows]);
    }

    /** Distinct Event-Typen des Mandanten — type + Anzahl Events (z. B. Filter-Chips). */
    public function types(Request $request)
    {
        $rows = DB::table('stored_events')
            ->where('meta_data->tenant_id', tenant()->getTenantKey())
            ->when($request->q, fn ($q) => $q->where('event_properties->type', 'like', '%'.$request->q.'%'))
            ->when($request->group, fn ($q) => $q->where('event_properties->type', 'like', $request->group.'.%'))
            ->when($request->groups, function ($q) use ($request) {
                $q->where(function ($w) use ($request) {
                    foreach (array_filter(array_map('trim', explode(',', $request->groups))) as $g) {
                        $w->orWhere('event_properties->type', 'like', $g.'.%');
                    }
                });
            })
            ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(`event_properties`, '$.type')) as type, COUNT(*) as events")
            ->groupBy('type')
            ->orderByDesc('events')
            ->get()
            ->map(fn ($r) => ['type' => $r->type, 'events' => (int) $r->events]);

        return response()->json(['data' => $rows]);
    }

    /** Distinct Betreffende (subject) des Mandanten — type, id, title, Anzahl Events (max 100). */
    public function subjects(Request $request)
    {
        $rows = DB::table('stored_events')
            ->where('meta_data->tenant_id', tenant()->getTenantKey())
            ->when($request->type, fn ($q) => $q->where('event_properties->subject->type', $request->type))
            ->when($request->q, fn ($q) => $q->where('event_properties->subject->title', 'like', '%'.$request->q.'%'))
            ->whereNotNull(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(`event_properties`, '$.subject.id'))"))
            ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(`event_properties`, '$.subject.type')) as subject_type, JSON_UNQUOTE(JSON_EXTRACT(`event_properties`, '$.subject.id')) as subject_id, MAX(JSON_UNQUOTE(JSON_EXTRACT(`event_properties`, '$.subject.title'))) as subject_title, COUNT(*) as events")
            ->groupBy('subject_type', 'subject_id')
            ->orderByDesc('events')
            ->limit(100)
            ->get()
            ->map(fn ($r) => ['type' => $r->subject_type, 'id' => $r->subject_id, 'title' => $r->subject_title, 'events' => (int) $r->events]);

        return response()->json(['data' => $rows]);
    }

    /** Audit-export: alle Events des Mandanten als NDJSON-Stream (gleiche Filter wie index). */
    public function export(Request $request)
    {
        $tenantKey = tenant()->getTenantKey();

        $query = DB::table('stored_events')
            ->where('meta_data->tenant_id', $tenantKey)
            ->when($request->id, fn ($q) => $q->where('id', $request->integer('id')))
            ->when($request->type, fn ($q) => $q->where('event_properties->type', $request->type))
            ->when($request->subject_id, fn ($q) => $q->where('event_properties->subject->id', $request->subject_id))
            ->when($request->subject_type, fn ($q) => $q->where('event_properties->subject->type', $request->subject_type))
            ->when($request->action, fn ($q) => $q->where('event_properties->type', 'like', '%.'.$request->action))
            ->when($request->actions, function ($q) use ($request) {
                $q->where(function ($w) use ($request) {
                    foreach (array_filter(array_map('trim', explode(',', $request->actions))) as $a) {
                        $w->orWhere('event_properties->type', 'like', '%.'.$a);
                    }
                });
            })
            ->when($request->group, fn ($q) => $q->where('event_properties->type', 'like', $request->group.'.%'))
            ->when($request->groups, function ($q) use ($request) {
                $q->where(function ($w) use ($request) {
                    foreach (array_filter(array_map('trim', explode(',', $request->groups))) as $g) {
                        $w->orWhere('event_properties->type', 'like', $g.'.%');
                    }
                });
            })
            ->when($request->q, fn ($q) => $q->where('event_properties->subject->title', 'like', '%'.$request->q.'%'))
            ->when($request->actor, fn ($q) => $q->where('meta_data->actor->id', $request->actor === 'me' ? $request->user()->id : $request->integer('actor')))
            ->when($request->actors, fn ($q) => $q->whereIn('meta_data->actor->id', array_map(fn ($a) => $a === 'me' ? $request->user()->id : (int) $a, array_filter(array_map('trim', explode(',', $request->actors))))))
            ->when($request->actor_name, fn ($q) => $q->where('meta_data->actor->name', 'like', '%'.$request->actor_name.'%'))
            ->when($request->types, fn ($q) => $q->whereIn('event_properties->type', array_filter(array_map('trim', explode(',', $request->types)))))
            ->when($request->since, fn ($q) => $q->where('created_at', '>=', $request->date('since')))
            ->when($request->until, fn ($q) => $q->where('created_at', '<=', $request->date('until')))
            ->when($request->day, fn ($q) => $q->whereDate('created_at', $request->date('day')))
            ->orderBy('id', $request->string('dir')->toString() === 'asc' ? 'asc' : 'desc')
            ->when($request->filled('limit'), fn ($q) => $q->limit(min($request->integer('limit'), 10000)))
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
