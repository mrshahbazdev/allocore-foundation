<?php

namespace Modules\Ai\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Ai\Console\AiCoachCommand;
use Nwidart\Modules\Support\ModuleServiceProvider;

class AiServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Ai';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'ai';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        AiCoachCommand::class,
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
        $schedule->command('ai:coach')->weeklyOn(1, '06:00');
    }
}
