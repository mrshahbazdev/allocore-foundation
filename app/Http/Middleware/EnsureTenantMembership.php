<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Middleware\InitializeTenancyByRequestData;

class EnsureTenantMembership extends InitializeTenancyByRequestData
{
    public function handle($request, Closure $next)
    {
        return parent::handle($request, function ($req) use ($next) {
            $user = $req->user();

            if ($user !== null) {
                $memberships = DB::table('model_has_roles')
                    ->where('model_type', $user::class)
                    ->where('model_id', $user->getAuthIdentifier());

                // Grace-Modus: Nutzer ohne jede Rollen-Zuweisung (z. B. frische
                // Installationen) duerfen weiterhin in jeden Mandanten — sonst
                // gaebe es keinen Einstiegspunkt, Mitglieder anzulegen.
                $hasAnyMembership = (clone $memberships)->exists();

                if ($hasAnyMembership) {
                    $isMember = (clone $memberships)
                        ->where('team_id', tenant()->getTenantKey())
                        ->exists();

                    abort_unless($isMember, 403, 'Kein Mitglied dieses Mandanten.');
                }
            }

            return $next($req);
        });
    }
}
