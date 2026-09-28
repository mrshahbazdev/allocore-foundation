<?php

namespace Modules\DataPlatform\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Modules\DataPlatform\Models\IntegrationConnector;

class ConnectorController extends Controller
{
    public function index(Request $request)
    {
        return IntegrationConnector::query()
            ->when($request->q, fn ($q) => $q->where('name', 'like', '%'.$request->q.'%'))
            ->latest('id')
            ->paginate(min($request->integer('per_page', 50), 200));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:1000',
            'headers' => 'nullable|array',
            'interval_minutes' => 'nullable|integer|min:5|max:10080',
            'active' => 'nullable|boolean',
        ]);

        $connector = IntegrationConnector::create($data + ['tenant_id' => (string) tenant()->getTenantKey()]);

        return response()->json($connector, 201);
    }

    public function update(Request $request, IntegrationConnector $connector)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'url' => 'sometimes|url|max:1000',
            'headers' => 'sometimes|nullable|array',
            'interval_minutes' => 'sometimes|integer|min:5|max:10080',
            'active' => 'sometimes|boolean',
        ]);
        $connector->update($data);

        return $connector;
    }

    public function destroy(IntegrationConnector $connector)
    {
        $connector->delete();

        return response()->noContent();
    }

    /** Sofort ausführen (unabhängig vom Intervall). */
    public function run(IntegrationConnector $connector)
    {
        Artisan::call('integrations:pull', ['--connector' => $connector->id]);
        $connector->refresh();

        return response()->json(['last_status' => $connector->last_status, 'last_run_at' => $connector->last_run_at]);
    }
}
