<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Modules\Core\Notifications\NewLoginAlert;
use Modules\DataPlatform\Events\DomainEvent;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();
        $ip = (string) $request->ip();
        if ($user->last_login_ip && $user->last_login_ip !== $ip) {
            $user->notify(new NewLoginAlert($ip, $user->last_login_ip));
        }
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $ip])->saveQuietly();

        $teamIds = DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->where('model_id', $user->id)
            ->pluck('team_id');
        foreach ($teamIds as $teamId) {
            $event = new DomainEvent(
                type: 'user.logged_in',
                tenantId: (string) $teamId,
                subject: ['type' => 'user', 'id' => $user->id, 'title' => $user->name],
                payload: ['ip' => $ip],
            );
            $event->setMetaData(['tenant_id' => (string) $teamId]);
            event($event);
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user) {
            $teamIds = DB::table('model_has_roles')
                ->where('model_type', User::class)
                ->where('model_id', $user->id)
                ->pluck('team_id');
            foreach ($teamIds as $teamId) {
                $event = new DomainEvent(
                    type: 'user.logged_out',
                    tenantId: (string) $teamId,
                    subject: ['type' => 'user', 'id' => $user->id, 'title' => $user->name],
                    payload: ['ip' => (string) $request->ip()],
                );
                $event->setMetaData(['tenant_id' => (string) $teamId]);
                event($event);
            }
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
