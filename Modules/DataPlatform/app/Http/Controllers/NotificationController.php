<?php

namespace Modules\DataPlatform\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $limit = min($request->integer('limit', 10), 50);

        return $request->user()->notifications()
            ->when($request->boolean('unread'), fn ($q) => $q->whereNull('read_at'))
            ->when($request->filled('kind'), fn ($q) => $q->where('data->kind', $request->string('kind')->toString()))
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'kind' => $n->data['kind'] ?? null,
                'title' => $n->data['title'] ?? '',
                'due_at' => $n->data['due_at'] ?? null,
                'entity_id' => $n->data['id'] ?? null,
                'read' => $n->read_at !== null,
                'created_at' => $n->created_at,
            ]);
    }

    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications->each->markAsRead();

        return response()->json(['status' => 'ok']);
    }

    public function unreadCount(Request $request)
    {
        return response()->json(['count' => $request->user()->unreadNotifications()->count()]);
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

    public function destroy(Request $request, string $id)
    {
        $request->user()->notifications()->where('id', $id)->firstOrFail()->delete();

        return response()->noContent();
    }
}
