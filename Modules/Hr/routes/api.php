<?php

use Illuminate\Support\Facades\Route;
use Modules\Hr\Http\Controllers\LeaveRequestController;

Route::middleware(['auth:sanctum', 'tenant.request', 'throttle:api'])->prefix('v1')->group(function () {
    Route::apiResource('leave-requests', LeaveRequestController::class)
        ->only(['index', 'show'])->middleware('permission:hr.view')->names('leave-requests');
    Route::apiResource('leave-requests', LeaveRequestController::class)
        ->only(['store', 'update', 'destroy'])->middleware('permission:hr.manage')->names('leave-requests');
});
