<?php

use Illuminate\Support\Facades\Route;
use Modules\Executive\Http\Controllers\ExecutiveController;

Route::middleware(['auth:sanctum', 'tenant.request', 'throttle:api'])->prefix('v1')->group(function () {
    Route::get('executive/overview', [ExecutiveController::class, 'overview'])
        ->middleware('permission:executive.view');
    Route::apiResource('exec-reports', ExecutiveController::class)
        ->only(['index', 'show'])->middleware('permission:executive.view');
    Route::apiResource('exec-reports', ExecutiveController::class)
        ->only(['store'])->middleware('permission:executive.manage');
});
