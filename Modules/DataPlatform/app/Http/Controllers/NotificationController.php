<?php

namespace Modules\DataPlatform\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $limit = min($request->integer('per_page', $request->integer('limit', 10)), 200);

        $muted = $request->user()->notification_muted ?? [];

        return $request->user()->notifications()
            ->when($request->boolean('unread'), fn ($q) => $q->whereNull('read_at'))
            ->when($request->boolean('read'), fn ($q) => $q->whereNotNull('read_at'))
            ->when($request->filled('muted'), fn ($q) => $request->boolean('muted')
                ? $q->whereIn('data->kind', $muted)
                : $q->whereNotIn('data->kind', $muted))
            ->when($request->filled('kind'), fn ($q) => $q->where('data->kind', $request->string('kind')->toString()))
            ->when($request->filled('code'), fn ($q) => $q->where('data->code', $request->string('code')->toString()))
            ->when($request->filled('before'), fn ($q) => $q->where('created_at', '<', $request->date('before')))
            ->when($request->filled('after'), fn ($q) => $q->where('created_at', '>=', $request->date('after')))
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'kind' => $n->data['kind'] ?? null,
                'title' => $n->data['title'] ?? '',
                'due_at' => $n->data['due_at'] ?? null,
                'entity_id' => $n->data['id'] ?? null,
                'code' => $n->data['code'] ?? null,
                'read' => $n->read_at !== null,
                'muted' => in_array($n->data['kind'] ?? null, $muted, true),
                'created_at' => $n->created_at,
            ]);
    }

    public function markAllRead(Request $request)
    {
        $muted = $request->user()->notification_muted ?? [];

        $request->user()->unreadNotifications()
            ->when($request->filled('kind'), fn ($q) => $q->where('data->kind', $request->string('kind')->toString()))
            ->when($request->filled('code'), fn ($q) => $q->where('data->code', $request->string('code')->toString()))
            ->when($request->boolean('muted') && $muted !== [], fn ($q) => $q->whereIn('data->kind', $muted))
            ->when($request->filled('before'), fn ($q) => $q->where('created_at', '<', $request->date('before')))
            ->when($request->filled('after'), fn ($q) => $q->where('created_at', '>=', $request->date('after')))
            ->update(['read_at' => now()]);

        return response()->json(['status' => 'ok']);
    }

    public function unreadCount(Request $request)
    {
        $muted = $request->user()->notification_muted ?? [];
        $q = $request->user()->unreadNotifications()
            ->when(! $request->boolean('include_muted') && ! $request->boolean('muted') && $muted !== [], fn ($q) => $q->whereNotIn('data->kind', $muted))
            ->when($request->filled('kind'), fn ($q) => $q->where('data->kind', $request->string('kind')->toString()))
            ->when($request->filled('code'), fn ($q) => $q->where('data->code', $request->string('code')->toString()))
            ->when($request->boolean('muted') && $muted !== [], fn ($q) => $q->whereIn('data->kind', $muted))
            ->when($request->filled('before'), fn ($q) => $q->where('created_at', '<', $request->date('before')))
            ->when($request->filled('after'), fn ($q) => $q->where('created_at', '>=', $request->date('after')));

        return response()->json(['count' => $q->count()]);
    }

    public function kinds(Request $request)
    {
        return response()->json(
            $request->user()->notifications()
                ->whereRaw("JSON_CONTAINS_PATH(`data`, 'one', '$.kind')")
                ->reorder()
                ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(`data`, '$.kind')) as kind")
                ->distinct()
                ->pluck('kind')
                ->sort()
                ->values()
        );
    }

    public function stats(Request $request)
    {
        $muted = $request->user()->notification_muted ?? [];
        $base = $request->user()->notifications()
            ->when($request->filled('kind'), fn ($q) => $q->where('data->kind', $request->string('kind')->toString()))
            ->when($request->filled('code'), fn ($q) => $q->where('data->code', $request->string('code')->toString()))
            ->when($request->filled('before'), fn ($q) => $q->where('created_at', '<', $request->date('before')))
            ->when($request->filled('after'), fn ($q) => $q->where('created_at', '>=', $request->date('after')))
            ->when($request->filled('muted') && $muted !== [], fn ($q) => $request->boolean('muted')
                ? $q->whereIn('data->kind', $muted)
                : $q->whereNotIn('data->kind', $muted));

        return response()->json([
            'total' => (clone $base)->count(),
            'unread' => (clone $base)->whereNull('read_at')->count(),
            'read' => (clone $base)->whereNotNull('read_at')->count(),
            'muted' => $muted === [] ? 0 : (clone $base)->whereIn('data->kind', $muted)->count(),
        ]);
    }

    public function codes(Request $request)
    {
        return response()->json(
            $request->user()->notifications()
                ->whereRaw("JSON_CONTAINS_PATH(`data`, 'one', '$.code')")
                ->reorder()
                ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(`data`, '$.code')) as code")
                ->distinct()
                ->pluck('code')
                ->sort()
                ->values()
        );
    }

    public function markRead(Request $request, string $id)
    {
        $n = $request->user()->notifications()->where('id', $id)->firstOrFail();
        $n->markAsRead();

        return response()->json(['status' => 'ok']);
    }

    public function markUnread(Request $request, string $id)
    {
        $n = $request->user()->notifications()->where('id', $id)->firstOrFail();
        $n->markAsUnread();

        return response()->json(['status' => 'ok']);
    }

    public function deleteRead(Request $request)
    {
        $muted = $request->user()->notification_muted ?? [];
        $count = $request->user()->notifications()->whereNotNull('read_at')
            ->when($request->filled('kind'), fn ($q) => $q->where('data->kind', $request->input('kind')))
            ->when($request->filled('code'), fn ($q) => $q->where('data->code', $request->string('code')->toString()))
            ->when($request->boolean('muted') && $muted !== [], fn ($q) => $q->whereIn('data->kind', $muted))
            ->when($request->filled('before'), fn ($q) => $q->where('created_at', '<', $request->date('before')))
            ->when($request->filled('after'), fn ($q) => $q->where('created_at', '>=', $request->date('after')))
            ->delete();

        return response()->json(['deleted' => $count]);
    }

    public function destroyAll(Request $request)
    {
        $muted = $request->user()->notification_muted ?? [];
        $count = $request->user()->notifications()
            ->when($request->filled('kind'), fn ($q) => $q->where('data->kind', $request->input('kind')))
            ->when($request->boolean('read'), fn ($q) => $q->whereNotNull('read_at'))
            ->when($request->boolean('unread'), fn ($q) => $q->whereNull('read_at'))
            ->when($request->filled('code'), fn ($q) => $q->where('data->code', $request->string('code')->toString()))
            ->when($request->boolean('muted') && $muted !== [], fn ($q) => $q->whereIn('data->kind', $muted))
            ->when($request->filled('before'), fn ($q) => $q->where('created_at', '<', $request->date('before')))
            ->when($request->filled('after'), fn ($q) => $q->where('created_at', '>=', $request->date('after')))
            ->delete();

        return response()->json(['deleted' => $count]);
    }

    public function destroy(Request $request, string $id)
    {
        $request->user()->notifications()->where('id', $id)->firstOrFail()->delete();

        return response()->noContent();
    }
}
