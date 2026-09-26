<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Middleware\PermissionMiddleware;

/**
 * Erweitert spatie/permission um Sanctum-Token-Abilities: hat ein
 * Personal-Access-Token eingeschraenkte Abilities (nicht '*'), muss
 * die geforderte Permission auch im Token enthalten sein.
 * Session-/TransientToken-Auth ist davon nicht betroffen.
 */
class AbilitiesPermissionMiddleware extends PermissionMiddleware
{
    public function handle(Request $request, Closure $next, $permission, ?string $guard = null)
    {
        $token = $request->user()?->currentAccessToken();

        // Nur echte API-Requests mit persistiertem Bearer-Token —
        // TransientToken-/Session-Auth und gemockte Token (Tests) bleiben unberuehrt.
        if ($request->bearerToken() && $token instanceof PersonalAccessToken && $token->exists) {
            foreach (explode('|', (string) $permission) as $perm) {
                $perm = trim($perm);
                abort_unless($perm === '' || $token->can($perm), 403, 'Token-Ability fehlt: '.$perm);
            }
        }

        return parent::handle($request, $next, $permission, $guard);
    }
}
