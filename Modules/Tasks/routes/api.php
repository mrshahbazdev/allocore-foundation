<?php

use Illuminate\Support\Facades\Route;
use Modules\Tasks\Http\Controllers\TasksController;

Route::middleware(['auth:sanctum', 'tenant.request'])->prefix('v1')->group(function () {
    Route::apiResource('tasks', TasksController::class)->names('tasks');
});
