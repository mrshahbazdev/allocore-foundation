<?php

namespace Modules\Audits\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class AuditsServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Audits';

    protected string $nameLower = 'audits';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];
}
