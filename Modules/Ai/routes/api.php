<?php

use Illuminate\Support\Facades\Route;
use Modules\Ai\Http\Controllers\AiAnalysisController;

Route::middleware(['auth:sanctum', 'tenant.request', 'throttle:api'])->prefix('v1')->group(function () {
    Route::apiResource('ai-analyses', AiAnalysisController::class)
        ->only(['index', 'show'])->middleware('permission:ai.view');
    Route::apiResource('ai-analyses', AiAnalysisController::class)
        ->only(['store'])->middleware('permission:ai.manage');
});
