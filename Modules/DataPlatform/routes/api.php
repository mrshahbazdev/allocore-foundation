<?php

use Illuminate\Support\Facades\Route;
use Modules\DataPlatform\Http\Controllers\EventController;
use Modules\DataPlatform\Http\Controllers\InsightController;
use Modules\DataPlatform\Http\Controllers\MetricController;

Route::middleware(['auth:sanctum', 'tenant.request', 'permission:metrics.view'])->prefix('v1')->group(function () {
    Route::get('events', [EventController::class, 'index'])->name('data-platform.events');
    Route::get('metrics', [MetricController::class, 'index'])->name('data-platform.metrics');
    Route::get('insights', [InsightController::class, 'index'])->name('data-platform.insights');
    Route::get('metrics/{metric}', [MetricController::class, 'show'])->name('data-platform.metrics.show');
});
