<?php

namespace App\Providers;

use App\Models\Tenant;
use Database\Seeders\RoleSeeder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // API-Rate-Limit: 120 Requests/Min je Nutzer (oder IP)
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        // Passwort-Policy (Registrierung, Reset, Aenderung): mind. 12 Zeichen,
        // Gross-/Kleinschreibung und Zahl — wirkt ueber Password::defaults().
        Password::defaults(fn () => Password::min(12)->mixedCase()->numbers());

        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Jeder Tenant bekommt automatisch das Rollenmodell (Dokument C),
        // egal ueber welchen Pfad er angelegt wurde.
        Tenant::created(function (Tenant $tenant) {
            RoleSeeder::forTenant($tenant);
        });
    }
}
