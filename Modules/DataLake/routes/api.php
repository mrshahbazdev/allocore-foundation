<?php

use Illuminate\Support\Facades\Route;
use Modules\DataLake\Http\Controllers\DataObjectController;

Route::middleware(['auth:sanctum', 'tenant.request'])->prefix('v1')->group(function () {
    Route::apiResource('data-objects', DataObjectController::class)
        ->only(['index', 'show'])->middleware('permission:datalake.view');
    Route::get('data-objects/{data_object}/download', [DataObjectController::class, 'download'])
        ->middleware('permission:datalake.view');
    Route::apiResource('data-objects', DataObjectController::class)
        ->only(['store', 'destroy'])->middleware('permission:datalake.manage');
});
