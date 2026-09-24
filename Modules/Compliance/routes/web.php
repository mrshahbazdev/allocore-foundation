<?php

use Illuminate\Support\Facades\Route;
use Modules\Compliance\Http\Controllers\ComplianceController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('compliances', ComplianceController::class)->names('compliance');
});
