<?php

use Illuminate\Support\Facades\Route;
use Modules\CorporateDev\Http\Controllers\MeasureController;
use Modules\CorporateDev\Http\Controllers\ProjectController;
use Modules\CorporateDev\Http\Controllers\StrategyController;

Route::middleware(['auth:sanctum', 'tenant.request', 'throttle:api'])->prefix('v1')->group(function () {
    foreach ([
        'strategies' => StrategyController::class,
        'projects' => ProjectController::class,
        'measures' => MeasureController::class,
    ] as $resource => $controller) {
        Route::apiResource($resource, $controller)
            ->only(['index', 'show'])->middleware('permission:projects.view')->names($resource);
        Route::apiResource($resource, $controller)
            ->only(['store', 'update', 'destroy'])->middleware('permission:projects.manage')->names($resource);
    }
});
