<?php

use App\Http\Middleware\AbilitiesPermissionMiddleware;
use App\Http\Middleware\EnsureTenantMembership;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedByRequestDataException;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            // Tenancy identification (R4 Mandantenfähigkeit)
            'tenant.domain' => InitializeTenancyByDomain::class,
            'tenant.request' => EnsureTenantMembership::class,
            'tenant.central-guard' => PreventAccessFromCentralDomains::class,
            // RBAC (Dokument C — spatie/laravel-permission)
            'role' => RoleMiddleware::class,
            'permission' => AbilitiesPermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        // Fehlender/unbekannter X-Tenant-Header → klare 400-Antwort statt 500.
        $exceptions->render(function (TenantCouldNotBeIdentifiedByRequestDataException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'X-Tenant Header fehlt oder Mandant unbekannt.',
                ], 400);
            }
        });
    })->create();
