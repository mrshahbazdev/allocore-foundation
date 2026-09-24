<?php

namespace Modules\Compliance\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Compliance\Console\RemindDueCompliance;
use Nwidart\Modules\Support\ModuleServiceProvider;

class ComplianceServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Compliance';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'compliance';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        RemindDueCompliance::class,
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
        $schedule->command('compliance:remind')->hourly();
    }
}
