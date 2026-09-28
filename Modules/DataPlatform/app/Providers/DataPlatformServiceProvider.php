<?php

namespace Modules\DataPlatform\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\DataPlatform\Console\AggregateMetrics;
use Modules\DataPlatform\Console\NotifyCriticalInsights;
use Modules\DataPlatform\Console\PruneNotifications;
use Modules\DataPlatform\Console\PullConnectorsCommand;
use Modules\DataPlatform\Support\ActivityRecorder;
use Nwidart\Modules\Support\ModuleServiceProvider;

class DataPlatformServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'DataPlatform';

    protected string $nameLower = 'dataplatform';

    protected array $commands = [
        AggregateMetrics::class,
        PruneNotifications::class,
        NotifyCriticalInsights::class,
        PullConnectorsCommand::class,
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
        $schedule->command('notifications:prune')->daily();
        $schedule->command('insights:notify')->daily();
        $schedule->command('insights:notify --warnings')->weeklyOn(1);
        $schedule->command('integrations:pull')->hourly();
    }
}
