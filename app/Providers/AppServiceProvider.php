<?php

namespace App\Providers;

use App\Models\Tenant;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\ServiceProvider;

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
        // Jeder Tenant bekommt automatisch das Rollenmodell (Dokument C),
        // egal ueber welchen Pfad er angelegt wurde.
        Tenant::created(function (Tenant $tenant) {
            RoleSeeder::forTenant($tenant);
        });
    }
}
