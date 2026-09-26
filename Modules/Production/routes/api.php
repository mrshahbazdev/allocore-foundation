<?php

use Illuminate\Support\Facades\Route;
use Modules\Production\Http\Controllers\MachineController;
use Modules\Production\Http\Controllers\ProductionOrderController;

Route::middleware(['auth:sanctum', 'tenant.request', 'throttle:api'])->prefix('v1')->group(function () {
    foreach ([
        'machines' => MachineController::class,
        'production-orders' => ProductionOrderController::class,
    ] as $resource => $controller) {
        Route::apiResource($resource, $controller)
            ->only(['index', 'show'])->middleware('permission:production.view')->names($resource);
        Route::apiResource($resource, $controller)
            ->only(['store', 'update', 'destroy'])->middleware('permission:production.manage')->names($resource);
    }
});
