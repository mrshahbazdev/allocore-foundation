<?php

namespace Modules\Core\Console;

use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;

class PruneTokens extends Command
{
    protected $signature = 'tokens:prune {--workspace-days=1 : Workspace-Session-Token aelter als N Tage loeschen}';

    protected $description = 'Loescht abgelaufene Personal Access Tokens und alte Workspace-Session-Tokens.';

    public function handle(): int
    {
        $expired = PersonalAccessToken::whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->delete();

        $days = max(1, (int) $this->option('workspace-days'));
        $stale = PersonalAccessToken::where('name', 'workspace')
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        $this->info("{$expired} abgelaufene + {$stale} alte Workspace-Token(s) geloescht.");

        return self::SUCCESS;
    }
}
