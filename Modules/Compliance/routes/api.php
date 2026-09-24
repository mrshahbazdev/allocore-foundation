<?php

use Illuminate\Support\Facades\Route;
use Modules\Compliance\Http\Controllers\ComplianceController;

Route::middleware(['auth:sanctum', 'tenant.request'])->prefix('v1')->group(function () {
    Route::apiResource('compliances', ComplianceController::class)->names('compliance');
});
