<?php

use Illuminate\Support\Facades\Route;
use Modules\Compliance\Http\Controllers\DeadlineController;
use Modules\Compliance\Http\Controllers\InspectionController;
use Modules\Compliance\Http\Controllers\InstructionController;
use Modules\Compliance\Http\Controllers\OperatingInstructionController;
use Modules\Compliance\Http\Controllers\RiskAssessmentController;

Route::middleware(['auth:sanctum', 'tenant.request'])->prefix('v1')->group(function () {
    foreach ([
        'instructions' => InstructionController::class,
        'inspections' => InspectionController::class,
        'deadlines' => DeadlineController::class,
        'risk-assessments' => RiskAssessmentController::class,
        'operating-instructions' => OperatingInstructionController::class,
    ] as $resource => $controller) {
        Route::apiResource($resource, $controller)
            ->only(['index', 'show'])->middleware('permission:compliance.view')->names("compliance.{$resource}");
        Route::apiResource($resource, $controller)
            ->only(['store', 'update', 'destroy'])->middleware('permission:compliance.manage')->names("compliance.{$resource}");
    }
});
