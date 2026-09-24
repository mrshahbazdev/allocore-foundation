<?php

use Illuminate\Support\Facades\Route;
use Modules\Documents\Http\Controllers\DocumentController;

Route::middleware(['auth:sanctum', 'tenant.request'])->prefix('v1')->group(function () {
    Route::apiResource('documents', DocumentController::class)
        ->only(['index', 'show'])->middleware('permission:documents.view')->names('documents');
    Route::apiResource('documents', DocumentController::class)
        ->only(['store', 'update', 'destroy'])->middleware('permission:documents.manage')->names('documents');
    Route::post('documents/{document}/versions', [DocumentController::class, 'uploadVersion'])
        ->middleware('permission:documents.manage')->name('documents.versions.store');
    Route::get('documents/{document}/download/{version?}', [DocumentController::class, 'download'])
        ->middleware('permission:documents.view')->name('documents.download');
});
