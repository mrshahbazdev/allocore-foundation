<?php

use Illuminate\Support\Facades\Route;
use Modules\ExpertNetwork\Http\Controllers\AnswerController;
use Modules\ExpertNetwork\Http\Controllers\ExpertProfileController;
use Modules\ExpertNetwork\Http\Controllers\QuestionController;
use Modules\ExpertNetwork\Http\Controllers\TenderApplicationController;
use Modules\ExpertNetwork\Http\Controllers\TenderController;

Route::middleware(['auth:sanctum', 'tenant.request', 'throttle:api'])->prefix('v1')->group(function () {
    Route::get('expert-profiles/match', [ExpertProfileController::class, 'match'])
        ->middleware('permission:experts.view')->name('expert-network.profiles.match');
    Route::apiResource('expert-profiles', ExpertProfileController::class)
        ->only(['index', 'show'])->middleware('permission:experts.view')->names('expert-network.profiles');
    Route::apiResource('expert-profiles', ExpertProfileController::class)
        ->only(['store', 'update', 'destroy'])->middleware('permission:experts.manage')->names('expert-network.profiles');

    Route::apiResource('questions', QuestionController::class)
        ->only(['index', 'show'])->middleware('permission:experts.view')->names('expert-network.questions');
    Route::apiResource('questions', QuestionController::class)
        ->only(['store', 'update', 'destroy'])->middleware('permission:experts.manage')->names('expert-network.questions');
    Route::post('questions/{question}/answers', [AnswerController::class, 'store'])
        ->middleware('permission:experts.manage')->name('expert-network.answers.store');
    Route::post('answers/{answer}/accept', [AnswerController::class, 'accept'])
        ->middleware('permission:experts.manage')->name('expert-network.answers.accept');
    Route::delete('answers/{answer}', [AnswerController::class, 'destroy'])
        ->middleware('permission:experts.manage')->name('expert-network.answers.destroy');

    Route::apiResource('tenders', TenderController::class)
        ->only(['index', 'show'])->middleware('permission:experts.view')->names('expert-network.tenders');
    Route::apiResource('tenders', TenderController::class)
        ->only(['store', 'update', 'destroy'])->middleware('permission:experts.manage')->names('expert-network.tenders');
    Route::post('tenders/{tender}/applications', [TenderApplicationController::class, 'store'])
        ->middleware('permission:experts.manage')->name('expert-network.applications.store');
    Route::patch('tender-applications/{tenderApplication}', [TenderApplicationController::class, 'update'])
        ->middleware('permission:experts.manage')->name('expert-network.applications.update');
    Route::delete('tender-applications/{tenderApplication}', [TenderApplicationController::class, 'destroy'])
        ->middleware('permission:experts.manage')->name('expert-network.applications.destroy');
});
