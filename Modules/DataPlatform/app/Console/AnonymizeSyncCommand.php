<?php

namespace Modules\DataPlatform\Console;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Person;
use Modules\DataPlatform\Models\AnonymizedRecord;

class AnonymizeSyncCommand extends Command
{
    protected $signature = 'anonymize:sync {--tenant= : Nur diesen Mandanten}';

    protected $description = 'Pseudonymisierte „digitale Zwillinge" je Datensatz aktualisieren (users, persons) — PII-freie Kopie für Analytics und KI-Coach';

    public function handle(): int
    {
        $tenantIds = $this->option('tenant')
            ? collect([(string) $this->option('tenant')])
            : DB::table('tenants')->pluck('id');

        $count = 0;
        foreach ($tenantIds as $tenantId) {
            $tenantId = (string) $tenantId;
            $tenant = Tenant::find($tenantId);
            if (! $tenant) {
                continue;
            }
            tenancy()->initialize($tenant);
            $count += $this->syncTenant($tenantId);
        }

        $this->info("{$count} anonymisierte Datensätze synchronisiert.");

        return self::SUCCESS;
    }

    private function syncTenant(string $tenantId): int
    {
        $count = 0;

        $memberIds = DB::table('model_has_roles')
            ->where('team_id', $tenantId)
            ->where('model_type', User::class)
            ->pluck('model_id');

        foreach (User::whereIn('id', $memberIds)->get() as $user) {
            $count++;
            $this->upsert($tenantId, 'user', $user->id, [
                'roles' => $user->getRoleNames()->all(),
                'last_login_date' => $user->last_login_at?->toDateString(),
                'member_since' => $user->created_at?->toDateString(),
                'active' => ! is_null($user->email_verified_at),
            ]);
        }

        foreach (Person::where('tenant_id', $tenantId)->with('company:id,name')->get() as $person) {
            $count++;
            $this->upsert($tenantId, 'person', $person->id, [
                'person_type' => $person->type,
                'company' => $person->company?->name,
                'since' => $person->created_at?->toDateString(),
            ]);
        }

        return $count;
    }

    private function upsert(string $tenantId, string $type, string|int $id, array $payload): void
    {
        AnonymizedRecord::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'source_type' => $type,
                'pseudonym' => AnonymizedRecord::pseudonymFor($type, $id),
            ],
            ['payload' => $payload, 'synced_at' => now()],
        );
    }
}
