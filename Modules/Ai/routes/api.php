<?php

use Illuminate\Support\Facades\Route;
use Modules\Ai\Http\Controllers\AiController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('ais', AiController::class)->names('ai');
});
