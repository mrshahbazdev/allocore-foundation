<?php

use Illuminate\Support\Facades\Route;
use Modules\Audits\Http\Controllers\AuditController;
use Modules\Audits\Http\Controllers\AuditFindingController;

Route::middleware(['auth:sanctum', 'tenant.request', 'throttle:api'])->prefix('v1')->group(function () {
    Route::apiResource('audits', AuditController::class)
        ->only(['index', 'show'])->middleware('permission:audits.view')->names('audits');
    Route::apiResource('audits', AuditController::class)
        ->only(['store', 'update', 'destroy'])->middleware('permission:audits.manage')->names('audits');
    Route::apiResource('audit-findings', AuditFindingController::class)
        ->only(['index', 'show'])->middleware('permission:audits.view')->names('audit-findings');
    Route::apiResource('audit-findings', AuditFindingController::class)
        ->only(['store', 'update', 'destroy'])->middleware('permission:audits.manage')->names('audit-findings');
});
