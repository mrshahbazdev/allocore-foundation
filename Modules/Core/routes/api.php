<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\CompanyController;
use Modules\Core\Http\Controllers\PersonController;
use Modules\Core\Http\Controllers\RoleController;

Route::middleware(['auth:sanctum', 'tenant.request'])->prefix('v1')->group(function () {
    Route::apiResource('companies', CompanyController::class)
        ->only(['index', 'show'])->middleware('permission:companies.view')->names('companies');
    Route::apiResource('companies', CompanyController::class)
        ->only(['store', 'update', 'destroy'])->middleware('permission:companies.manage')->names('companies');

    Route::apiResource('persons', PersonController::class)
        ->only(['index', 'show'])->middleware('permission:persons.view')->names('persons');
    Route::apiResource('persons', PersonController::class)
        ->only(['store', 'update', 'destroy'])->middleware('permission:persons.manage')->names('persons');

    Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('users', [RoleController::class, 'users'])->name('users.index');
    Route::get('users/{user}/roles', [RoleController::class, 'userRoles'])->name('users.roles.show');
    Route::put('users/{user}/roles', [RoleController::class, 'assign'])
        ->middleware('permission:roles.manage')
        ->name('users.roles.assign');

    Route::post('demo-seed', function (Request $request) {
        Artisan::call('demo:seed', ['tenant' => (string) $request->header('X-Tenant')]);

        return response()->json(['status' => 'ok']);
    })->middleware('permission:roles.manage')->name('demo-seed');
});
