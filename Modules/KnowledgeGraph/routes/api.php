<?php

use Illuminate\Support\Facades\Route;
use Modules\KnowledgeGraph\Http\Controllers\GraphEdgeController;
use Modules\KnowledgeGraph\Http\Controllers\GraphEntityController;
use Modules\KnowledgeGraph\Http\Controllers\GraphTraversalController;

Route::middleware(['auth:sanctum', 'tenant.request'])->prefix('v1')->group(function () {
    foreach ([
        'graph-entities' => GraphEntityController::class,
        'graph-edges' => GraphEdgeController::class,
    ] as $resource => $controller) {
        Route::apiResource($resource, $controller)
            ->only(['index', 'show'])->middleware('permission:graph.view')->names($resource);
        Route::apiResource($resource, $controller)
            ->only(['store', 'update', 'destroy'])->middleware('permission:graph.manage')->names($resource);
    }
    Route::get('graph-entities/{graph_entity}/neighbors', [GraphTraversalController::class, 'neighbors'])
        ->middleware('permission:graph.view');
});
