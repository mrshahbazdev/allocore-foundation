<?php

use Illuminate\Support\Facades\Route;
use Modules\Tasks\Http\Controllers\TaskController;

Route::middleware(['auth:sanctum', 'tenant.request', 'throttle:api'])->prefix('v1')->group(function () {
    Route::apiResource('tasks', TaskController::class)
        ->only(['index', 'show'])->middleware('permission:tasks.view')->names('tasks');
    Route::apiResource('tasks', TaskController::class)
        ->only(['store', 'update', 'destroy'])->middleware('permission:tasks.manage')->names('tasks');
});
