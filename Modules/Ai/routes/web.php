<?php

use Illuminate\Support\Facades\Route;
use Modules\Ai\Http\Controllers\AiController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('ais', AiController::class)->names('ai');
});
