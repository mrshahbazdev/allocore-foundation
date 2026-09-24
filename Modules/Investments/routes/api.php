<?php

use Illuminate\Support\Facades\Route;
use Modules\Investments\Http\Controllers\InvestmentController;
use Modules\Investments\Http\Controllers\PortfolioController;

Route::middleware(['auth:sanctum', 'tenant.request'])->prefix('v1')->group(function () {
    foreach ([
        'portfolios' => PortfolioController::class,
        'investments' => InvestmentController::class,
    ] as $resource => $controller) {
        Route::apiResource($resource, $controller)
            ->only(['index', 'show'])->middleware('permission:investments.view')->names($resource);
        Route::apiResource($resource, $controller)
            ->only(['store', 'update', 'destroy'])->middleware('permission:investments.manage')->names($resource);
    }
});
