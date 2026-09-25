<?php

namespace Modules\KnowledgeGraph\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\KnowledgeGraph\Models\GraphEdge;

class GraphEdgeController extends Controller
{
    public function index(Request $request)
    {
        return GraphEdge::with(['from:id,name,type', 'to:id,name,type'])
            ->when($request->relation, fn ($q, $r) => $q->where('relation', $r))
            ->paginate(min(request()->integer('per_page', 200), 200));
    }

    public function store(Request $request)
    {
        $tenantKey = tenant()->getTenantKey();

        $validated = $request->validate([
            'from_entity_id' => ['required', Rule::exists('graph_entities', 'id')->where('tenant_id', $tenantKey)],
            'to_entity_id' => ['required', Rule::exists('graph_entities', 'id')->where('tenant_id', $tenantKey)],
            'relation' => ['required', 'string', 'max:100'],
            'properties' => ['nullable', 'array'],
        ]);

        $edge = GraphEdge::firstOrCreate(
            $request->only(['from_entity_id', 'to_entity_id', 'relation']),
            ['properties' => $validated['properties'] ?? null],
        );

        return response()->json($edge, 201);
    }

    public function show(GraphEdge $graphEdge)
    {
        return $graphEdge->load(['from:id,name,type', 'to:id,name,type']);
    }

    public function destroy(GraphEdge $graphEdge)
    {
        $graphEdge->delete();

        return response()->noContent();
    }
}
