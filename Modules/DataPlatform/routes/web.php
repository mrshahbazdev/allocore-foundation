<?php

use Illuminate\Support\Facades\Route;
use Modules\DataPlatform\Http\Controllers\ConnectAuthorizeController;

Route::middleware(['auth', 'verified'])->group(function () {
    // "Mit Allocore Manager verbinden" — Suite redirectet den Admin hierhin.
    Route::get('connect/authorize', [ConnectAuthorizeController::class, 'create'])
        ->name('data-platform.connect.authorize');
    Route::post('connect/authorize', [ConnectAuthorizeController::class, 'store'])
        ->name('data-platform.connect.authorize.store');
});
