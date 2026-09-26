<?php

namespace Modules\Core\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Core\Console\PruneTokens;
use Nwidart\Modules\Support\ModuleServiceProvider;

class CoreServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Core';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'core';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        PruneTokens::class,
    ];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /**
     * Define module schedules.
     */
    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->command('tokens:prune')->daily();
    }
}
