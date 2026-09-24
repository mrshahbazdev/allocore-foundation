<?php

namespace Modules\DataPlatform\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\DataPlatform\Console\AggregateMetrics;
use Modules\DataPlatform\Support\ActivityRecorder;
use Nwidart\Modules\Support\ModuleServiceProvider;

class DataPlatformServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'DataPlatform';

    protected string $nameLower = 'dataplatform';

    protected array $commands = [
        AggregateMetrics::class,
    ];

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        ActivityRecorder::register();
    }

    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->command('analytics:aggregate')->daily();
    }
}
