<?php

namespace Modules\Tasks\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Tasks\Console\RemindDueTasks;
use Nwidart\Modules\Support\ModuleServiceProvider;

class TasksServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Tasks';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'tasks';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        RemindDueTasks::class,
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
        $schedule->command('tasks:remind')->hourly();
    }
}
