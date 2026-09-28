<?php

namespace Modules\DataPlatform\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\DataPlatform\Events\DomainEvent;
use Modules\DataPlatform\Models\IntegrationSource;

class IntegrationController extends Controller
{
    public function index(Request $request)
    {
        return IntegrationSource::query()
            ->when($request->q, fn ($q) => $q->where('name', 'like', '%'.$request->q.'%'))
            ->latest('id')
            ->paginate(min($request->integer('per_page', 50), 200));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'kind' => 'nullable|string|max:50',
        ]);

        $source = IntegrationSource::create([
            'tenant_id' => (string) tenant()->getTenantKey(),
            'name' => $data['name'],
            'kind' => $data['kind'] ?? 'webhook',
            'token' => IntegrationSource::generateToken(),
        ]);

        $source->webhook_url = url('/api/v1/webhooks/'.$source->token);

        return response()->json($source, 201);
    }

    public function update(Request $request, IntegrationSource $integration)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'active' => 'sometimes|boolean',
        ]);
        $integration->update($data);

        return $integration;
    }

    public function destroy(IntegrationSource $integration)
    {
        $integration->delete();

        return response()->noContent();
    }

    /** Öffentlicher Webhook-Eingang: POST /api/v1/webhooks/{token} — kein Auth, Token identifiziert Mandant+Quelle. */
    public function webhook(Request $request, string $token)
    {
        $source = IntegrationSource::query()->where('token', $token)->where('active', true)->first();
        abort_unless($source, 404);

        $data = $request->validate([
            'type' => 'nullable|string|max:100|regex:/^[a-z0-9_]+$/',
            'subject' => 'nullable|array',
            'subject.type' => 'required_with:subject|string|max:100',
            'subject.id' => 'nullable|string|max:100',
            'subject.title' => 'nullable|string|max:500',
        ]);

        $tenantKey = $source->tenant_id;
        $kind = $data['type'] ?? 'received';

        $event = new DomainEvent(
            type: 'webhook.'.$kind,
            tenantId: $tenantKey,
            subject: $data['subject'] ?? ['type' => 'integration_source', 'id' => (string) $source->id, 'title' => $source->name],
            payload: [
                'source_id' => $source->id,
                'source_name' => $source->name,
                'body' => $request->except(['type', 'subject']),
            ],
        );
        $event->setMetaData(['tenant_id' => $tenantKey]);
        event($event);

        $source->update(['last_received_at' => now()]);

        return response()->json(['status' => 'recorded', 'id' => $event->storedEventId() ?? null], 201);
    }
}
