<?php

namespace Modules\Ai\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Ai\Jobs\RunAiCoachForTenant;
use Modules\Ai\Support\AiService;

class AiCoachCommand extends Command
{
    protected $signature = 'ai:coach {--tenant= : Nur diesen Mandanten bearbeiten} {--sync : Jobs synchron ausfuehren statt auf die Queue}';

    protected $description = 'KI-Coach: wöchentliche Analyse je Mandant als Queue-Job dispatchen';

    public function handle(): int
    {
        $tenantIds = $this->option('tenant')
            ? collect([(string) $this->option('tenant')])
            : DB::table('tenants')->pluck('id');

        $n = 0;
        foreach ($tenantIds as $tenantId) {
            if ($this->option('sync')) {
                (new RunAiCoachForTenant((string) $tenantId))->handle(app(AiService::class));
            } else {
                RunAiCoachForTenant::dispatch((string) $tenantId);
            }
            $n++;
        }

        $this->info("{$n} Coach-Job(s) ".($this->option('sync') ? 'ausgeführt.' : 'dispatched.'));

        return self::SUCCESS;
    }
}
