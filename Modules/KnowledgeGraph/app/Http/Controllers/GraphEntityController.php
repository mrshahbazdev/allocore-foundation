<?php

namespace Modules\KnowledgeGraph\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\KnowledgeGraph\Models\GraphEntity;

class GraphEntityController extends Controller
{
    public function index(Request $request)
    {
        return GraphEntity::query()
            ->when($request->q, fn ($q, $s) => $q->where('name', 'like', '%'.$s.'%'))
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->paginate(min(request()->integer('per_page', 200), 200));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'subject_type' => ['nullable', 'string', 'max:255'],
            'subject_id' => ['nullable', 'integer'],
            'properties' => ['nullable', 'array'],
        ]);

        $entity = GraphEntity::create($validated);

        return response()->json($entity, 201);
    }

    public function show(GraphEntity $graphEntity)
    {
        return $graphEntity->load([
            'outgoing.to:id,name,type', 'incoming.from:id,name,type', 'subject',
        ]);
    }

    public function update(Request $request, GraphEntity $graphEntity)
    {
        $validated = $request->validate([
            'type' => ['sometimes', 'string', 'max:100'],
            'name' => ['sometimes', 'string', 'max:255'],
            'properties' => ['nullable', 'array'],
        ]);

        $graphEntity->update($validated);

        return $graphEntity;
    }

    public function destroy(GraphEntity $graphEntity)
    {
        $graphEntity->delete();

        return response()->noContent();
    }
}
