<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\CompanyController;
use Modules\Core\Http\Controllers\PersonController;
use Modules\Core\Http\Controllers\RoleController;

Route::middleware(['auth:sanctum', 'tenant.request'])->prefix('v1')->group(function () {
    Route::apiResource('companies', CompanyController::class)->names('companies');
    Route::apiResource('persons', PersonController::class)->names('persons');
    Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('users/{user}/roles', [RoleController::class, 'userRoles'])->name('users.roles.show');
    Route::put('users/{user}/roles', [RoleController::class, 'assign'])
        ->middleware('role:holding|administrator|geschaeftsfuehrer')
        ->name('users.roles.assign');
});
