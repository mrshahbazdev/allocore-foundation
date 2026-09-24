<?php

use Illuminate\Support\Facades\Route;
use Modules\Participations\Http\Controllers\ParticipationController;

Route::middleware(['auth:sanctum', 'tenant.request'])->prefix('v1')->group(function () {
    Route::apiResource('participations', ParticipationController::class)
        ->only(['index', 'show'])->middleware('permission:participations.view')->names('participations');
    Route::apiResource('participations', ParticipationController::class)
        ->only(['store', 'update', 'destroy'])->middleware('permission:participations.manage')->names('participations');
});
