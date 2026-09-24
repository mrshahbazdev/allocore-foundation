<?php

use Illuminate\Support\Facades\Route;
use Modules\Executive\Http\Controllers\ExecutiveController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('executives', ExecutiveController::class)->names('executive');
});
