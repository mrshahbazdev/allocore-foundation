<?php

use Illuminate\Support\Facades\Route;
use Modules\Documents\Http\Controllers\DocumentController;

Route::middleware(['auth:sanctum', 'tenant.request'])->prefix('v1')->group(function () {
    Route::apiResource('documents', DocumentController::class)->names('documents');
    Route::post('documents/{document}/versions', [DocumentController::class, 'uploadVersion'])->name('documents.versions.store');
    Route::get('documents/{document}/download/{version?}', [DocumentController::class, 'download'])->name('documents.download');
});
