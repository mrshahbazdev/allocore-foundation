<?php

declare(strict_types=1);

use App\Models\Tenant;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — /api/v1 (R3 API First)
|--------------------------------------------------------------------------
|
| Central routes (tenant provisioning, auth) run without tenancy.
| Tenant-scoped routes initialize tenancy by request data:
| send `X-Tenant: <tenant-id>` header (or `tenant` payload field).
|
*/

Route::prefix('v1')->group(function () {
    Route::get('/health', fn () => response()->json(['status' => 'ok', 'platform' => 'allocore-foundation']));

    // Central: tenant (Unternehmen/Mandant) provisioning
    Route::post('/tenants', function (Request $request) {
        $validated = $request->validate([
            'id' => ['nullable', 'string'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $tenant = Tenant::create($validated);

        RoleSeeder::forTenant($tenant);

        return response()->json($tenant, 201);
    });

    Route::get('/tenants', fn () => response()->json(Tenant::paginate()));

    // Tenant-scoped API surface — modules register their routes under this group.
    Route::middleware(['auth:sanctum', 'tenant.request'])->group(function () {
        Route::get('/context', function (Request $request) {
            return response()->json([
                'tenant' => tenant(),
                'user' => $request->user()?->only('id', 'name', 'email'),
            ]);
        });
    });
});
