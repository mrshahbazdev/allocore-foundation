<?php

namespace Modules\KnowledgeGraph\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\KnowledgeGraph\Models\GraphEdge;
use Modules\KnowledgeGraph\Models\GraphEntity;

/**
 * Knowledge Graph traversal: breadth-first neighborhood expansion.
 * GET /graph-entities/{id}/neighbors?depth=2 → nodes + edges up to depth.
 */
class GraphTraversalController extends Controller
{
    public function neighbors(GraphEntity $graphEntity)
    {
        $depth = min((int) request('depth', 1), 3);
        $seen = [$graphEntity->id => $graphEntity->only(['id', 'type', 'name'])];
        $edges = collect();
        $frontier = collect([$graphEntity->id]);

        for ($i = 0; $i < $depth && $frontier->isNotEmpty(); $i++) {
            $hop = GraphEdge::with(['from:id,name,type', 'to:id,name,type'])
                ->whereIn('from_entity_id', $frontier)->orWhereIn('to_entity_id', $frontier)->get();
            $edges = $edges->merge($hop);
            $frontier = $hop->flatMap(fn ($e) => [$e->from_entity_id, $e->to_entity_id])
                ->unique()->diff(array_keys($seen))->values();
            foreach ($frontier as $id) {
                $e = $hop->firstWhere('from_entity_id', $id) ?? $hop->firstWhere('to_entity_id', $id);
                $entity = $e->from_entity_id === $id ? $e->from : $e->to;
                $seen[$id] = $entity->only(['id', 'type', 'name']);
            }
        }

        return [
            'nodes' => array_values($seen),
            'edges' => $edges->unique('id')->map(fn ($e) => [
                'id' => $e->id, 'from' => $e->from_entity_id,
                'to' => $e->to_entity_id, 'relation' => $e->relation,
            ])->values(),
        ];
    }
}
