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
                // Strikte Mitgliedschaft: der Mandanten-Ersteller wird automatisch
                // administrator (Einstiegspunkt), weitere Nutzer werden per
                // POST /users eines Mitglieds angebunden — ein Nutzer ohne
                // Zuordnung hat in keinem fremden Mandanten etwas zu suchen.
                $isMember = DB::table('model_has_roles')
                    ->where('model_type', $user::class)
                    ->where('model_id', $user->getAuthIdentifier())
                    ->where('team_id', tenant()->getTenantKey())
                    ->exists();

                abort_unless($isMember, 403, 'Kein Mitglied dieses Mandanten.');
            }

            return $next($req);
        });
    }
}
