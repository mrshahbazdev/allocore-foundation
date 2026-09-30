<?php

namespace Modules\DataPlatform\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * OAuth-ähnlicher "Mit Allocore Manager verbinden"-Flow:
 * Die Suite leitet den Admin hierhin (redirect_uri + state).
 * Nach Anmeldung und Mandantenwahl erzeugen wir einen
 * einmaligen Code und leiten zur Suite-Callback-URL zurück.
 * Die Suite tauscht den Code via POST /api/v1/connect/exchange
 * gegen eine fertige Webhook-URL.
 */
class ConnectAuthorizeController extends Controller
{
    private const CODE_TTL = 300; // 5 Minuten

    public function create(Request $request): View|RedirectResponse
    {
        $data = $request->validate([
            'redirect_uri' => ['required', 'url', 'starts_with:https://'],
            'state' => 'nullable|string|max:255',
            'source_name' => 'nullable|string|max:255',
        ]);

        $tenants = $this->tenantsOf($request->user());
        if ($tenants->isEmpty()) {
            abort(403, 'Kein Mandant zugeordnet.');
        }

        return view('dataplatform::connect.authorize', [
            'tenants' => $tenants,
            'redirectUri' => $data['redirect_uri'],
            'state' => $data['state'] ?? '',
            'sourceName' => $data['source_name'] ?? 'Allocore Suite',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'redirect_uri' => ['required', 'url', 'starts_with:https://'],
            'state' => 'nullable|string|max:255',
            'source_name' => 'nullable|string|max:255',
            'tenant_id' => 'required|string|max:255',
        ]);

        $tenant = $this->tenantsOf($request->user())->firstWhere('id', $data['tenant_id']);
        abort_unless($tenant, 403, 'Kein Mitglied dieses Mandanten.');

        $code = 'ac_'.bin2hex(random_bytes(24));
        Cache::put('connect_code:'.$code, [
            'tenant_id' => (string) $tenant->id,
            'tenant_name' => (string) $tenant->name,
            'source_name' => $data['source_name'] ?? 'Allocore Suite',
            'user_id' => $request->user()->id,
        ], self::CODE_TTL);

        $separator = str_contains($data['redirect_uri'], '?') ? '&' : '?';
        $query = ['code' => $code];
        if (! empty($data['state'])) {
            $query['state'] = $data['state'];
        }

        return redirect()->to($data['redirect_uri'].$separator.http_build_query($query));
    }

    private function tenantsOf($user): Collection
    {
        $teamIds = DB::table('model_has_roles')
            ->where('model_type', $user::class)
            ->where('model_id', $user->id)
            ->pluck('team_id');

        return Tenant::whereIn('id', $teamIds)->get(['id', 'data']);
    }
}
