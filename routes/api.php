<?php

declare(strict_types=1);

use App\Models\Tenant;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Modules\DataPlatform\Events\DomainEvent;

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
    // Liveness + readiness: DB-Verbindung und ausstehende Migrationen prüfen.
    Route::get('/health', function () {
        $checks = [];
        $ok = true;

        try {
            DB::connection()->selectOne('select 1');
            $checks['database'] = 'ok';
        } catch (Throwable $e) {
            $checks['database'] = 'error';
            $ok = false;
        }

        try {
            $migrator = app('migrator');
            $files = $migrator->getMigrationFiles($migrator->paths());
            $ran = $migrator->getRepository()->getRan();
            $pending = array_diff(array_keys($files), $ran);
            $checks['migrations'] = count($pending) === 0 ? 'ok' : 'pending:'.count($pending);
            $ok = $ok && count($pending) === 0;
        } catch (Throwable $e) {
            $checks['migrations'] = 'unknown';
        }

        return response()->json([
            'status' => $ok ? 'ok' : 'degraded',
            'platform' => 'allocore-foundation',
            'checks' => $checks,
        ], $ok ? 200 : 503);
    });

    // Build-/Betriebs-Infos für Monitoring und Deploy-Verifikation.
    Route::get('/version', function () {
        $commit = null;
        try {
            $head = @file_get_contents(base_path('.git/HEAD'));
            if ($head !== false) {
                $head = trim($head);
                if (str_starts_with($head, 'ref:')) {
                    $ref = trim(substr($head, 4));
                    $refFile = base_path('.git/'.$ref);
                    $commit = is_file($refFile) ? substr(trim(file_get_contents($refFile)), 0, 12) : null;
                } else {
                    $commit = substr($head, 0, 12);
                }
            }
        } catch (Throwable) {
            $commit = null;
        }

        return response()->json([
            'platform' => 'allocore-foundation',
            'app_version' => env('APP_VERSION', 'dev'),
            'laravel' => app()->version(),
            'php' => PHP_VERSION,
            'commit' => $commit,
        ]);
    });

    // Central: tenant (Unternehmen/Mandant) provisioning
    Route::post('/tenants', function (Request $request) {
        $validated = $request->validate([
            'id' => ['nullable', 'string'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $tenant = Tenant::create($validated);

        RoleSeeder::forTenant($tenant);

        $event = new DomainEvent(
            type: 'tenant.created',
            tenantId: (string) $tenant->getTenantKey(),
            subject: ['type' => 'tenant', 'id' => $tenant->getTenantKey(), 'title' => $tenant->name],
        );
        $event->setMetaData(['tenant_id' => (string) $tenant->getTenantKey()]);
        event($event);

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
