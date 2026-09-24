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
        Tenant::created(function (Tenant $tenant) {
            RoleSeeder::forTenant($tenant);
        });
    }
}
