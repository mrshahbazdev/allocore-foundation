<?php

use Illuminate\Support\Facades\Route;
use Modules\Compliance\Http\Controllers\DeadlineController;
use Modules\Compliance\Http\Controllers\InspectionController;
use Modules\Compliance\Http\Controllers\InstructionController;
use Modules\Compliance\Http\Controllers\OperatingInstructionController;
use Modules\Compliance\Http\Controllers\RiskAssessmentController;

Route::middleware(['auth:sanctum', 'tenant.request'])->prefix('v1')->group(function () {
    Route::apiResource('instructions', InstructionController::class)->names('compliance.instructions');
    Route::apiResource('inspections', InspectionController::class)->names('compliance.inspections');
    Route::apiResource('deadlines', DeadlineController::class)->names('compliance.deadlines');
    Route::apiResource('risk-assessments', RiskAssessmentController::class)->names('compliance.risk-assessments');
    Route::apiResource('operating-instructions', OperatingInstructionController::class)->names('compliance.operating-instructions');
});
