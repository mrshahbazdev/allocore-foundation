<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\CompanyController;
use Modules\Core\Http\Controllers\PersonController;
use Modules\Core\Http\Controllers\RoleController;
use Modules\Core\Http\Controllers\TokenController;
use Modules\DataPlatform\Events\DomainEvent;

Route::middleware(['auth:sanctum', 'tenant.request', 'throttle:api'])->prefix('v1')->group(function () {
    Route::apiResource('companies', CompanyController::class)
        ->only(['index', 'show'])->middleware('permission:companies.view')->names('companies');
    Route::apiResource('companies', CompanyController::class)
        ->only(['store', 'update', 'destroy'])->middleware('permission:companies.manage')->names('companies');

    Route::apiResource('persons', PersonController::class)
        ->only(['index', 'show'])->middleware('permission:persons.view')->names('persons');
    Route::apiResource('persons', PersonController::class)
        ->only(['store', 'update', 'destroy'])->middleware('permission:persons.manage')->names('persons');

    Route::get('me', [RoleController::class, 'me'])->name('me.show');
    Route::put('me', [RoleController::class, 'updateMe'])->name('me.update');
    Route::delete('me/membership', [RoleController::class, 'leaveTenant'])->name('me.membership.leave');
    Route::put('me/password', [RoleController::class, 'updatePassword'])->name('me.password');
    Route::get('tokens/abilities', [TokenController::class, 'abilities'])->name('tokens.abilities');
    Route::get('tokens', [TokenController::class, 'index'])->name('tokens.index');
    Route::post('tokens', [TokenController::class, 'store'])->name('tokens.store');
    Route::delete('tokens/{id}', [TokenController::class, 'destroy'])->name('tokens.destroy');
    Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('permissions', [RoleController::class, 'permissions'])->name('permissions.index');
    Route::post('roles', [RoleController::class, 'storeRole'])
        ->middleware('permission:roles.manage')
        ->name('roles.store');
    Route::put('roles/{role}', [RoleController::class, 'updateRole'])
        ->middleware('permission:roles.manage')
        ->name('roles.update');
    Route::delete('roles/{role}', [RoleController::class, 'destroyRole'])
        ->middleware('permission:roles.manage')
        ->name('roles.destroy');
    Route::get('users', [RoleController::class, 'users'])->name('users.index');
    Route::post('users', [RoleController::class, 'store'])
        ->middleware('permission:roles.manage')
        ->name('users.store');
    Route::get('users/{user}/roles', [RoleController::class, 'userRoles'])->name('users.roles.show');
    Route::put('users/{user}/roles', [RoleController::class, 'assign'])
        ->middleware('permission:roles.manage')
        ->name('users.roles.assign');
    Route::delete('users/{user}', [RoleController::class, 'remove'])
        ->middleware('permission:roles.manage')
        ->name('users.remove');

    Route::get('tenant', function (Request $request) {
        $tenant = tenant();
        $members = DB::table('model_has_roles')
            ->where('team_id', (string) $tenant->getTenantKey())
            ->count();

        return response()->json([
            'id' => (string) $tenant->getTenantKey(),
            'name' => $tenant->name,
            'members_count' => $members,
            'created_at' => $tenant->created_at,
        ]);
    })->name('tenant.show');

    Route::put('tenant', function (Request $request) {
        $validated = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $tenant = tenant();
        $tenant->name = $validated['name'];
        $tenant->save();

        $event = new DomainEvent(
            type: 'tenant.updated',
            tenantId: (string) $tenant->getTenantKey(),
            subject: ['type' => 'tenant', 'id' => $tenant->getTenantKey(), 'title' => $tenant->name],
        );
        $event->setMetaData(['tenant_id' => (string) $tenant->getTenantKey()]);
        event($event);

        return response()->json($tenant);
    })->middleware('permission:roles.manage')->name('tenant.update');

    Route::post('demo-seed', function (Request $request) {
        Artisan::call('demo:seed', ['tenant' => (string) $request->header('X-Tenant')]);

        return response()->json(['status' => 'ok']);
    })->middleware('permission:roles.manage')->name('demo-seed');
});

// Ohne Tenant-Kontext: nur erreichbar, wenn keine Mitgliedschaften mehr bestehen.
Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('v1')->group(function () {
    Route::delete('me', [RoleController::class, 'deleteMe'])->name('me.delete');
});
