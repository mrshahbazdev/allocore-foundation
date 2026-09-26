<?php

use Illuminate\Support\Facades\Route;
use Modules\Finance\Http\Controllers\FinancialReportController;

Route::middleware(['auth:sanctum', 'tenant.request', 'throttle:api'])->prefix('v1')->group(function () {
    Route::apiResource('financial-reports', FinancialReportController::class)
        ->only(['index', 'show'])->middleware('permission:finance.view')->names('financial-reports');
    Route::apiResource('financial-reports', FinancialReportController::class)
        ->only(['store', 'update', 'destroy'])->middleware('permission:finance.manage')->names('financial-reports');
});
