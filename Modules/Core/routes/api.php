<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\CompanyController;
use Modules\Core\Http\Controllers\PersonController;

Route::middleware(['auth:sanctum', 'tenant.request'])->prefix('v1')->group(function () {
    Route::apiResource('companies', CompanyController::class)->names('companies');
    Route::apiResource('persons', PersonController::class)->names('persons');
});
