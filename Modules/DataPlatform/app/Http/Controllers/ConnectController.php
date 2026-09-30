<?php

namespace Modules\DataPlatform\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\DataPlatform\Models\IntegrationSource;

/**
 * Externe Suite-Anbindung: POST /api/v1/connect.
 * Ein Admin einer Suite-Installation meldet sich mit seinen
 * Manager-Zugangsdaten an; wir listen seine Mandanten oder legen
 * (bei tenant_id) direkt eine Webhook-Quelle an und liefern die URL.
 */
class ConnectController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'source_name' => 'nullable|string|max:255',
            'tenant_id' => 'nullable|string|max:255',
        ]);

        $user = User::where('email', $data['email'])->first();
        abort_unless($user && Hash::check($data['password'], $user->password), 422, 'Ungültige Zugangsdaten.');

        $tenants = $this->tenantsOf($user);
        abort_if($tenants->isEmpty(), 403, 'Kein Mandant zugeordnet.');

        if (empty($data['tenant_id'])) {
            return response()->json([
                'tenants' => $tenants->map(fn ($t) => [
                    'id' => $t->getTenantKey(),
                    'name' => $t->name,
                ])->values(),
            ]);
        }

        $tenant = $tenants->firstWhere('id', $data['tenant_id']);
        abort_unless($tenant, 403, 'Kein Mitglied dieses Mandanten.');

        $source = IntegrationSource::create([
            'tenant_id' => (string) $tenant['id'],
            'name' => $data['source_name'] ?? 'Allocore Suite',
            'kind' => 'webhook',
            'token' => IntegrationSource::generateToken(),
        ]);

        return response()->json([
            'tenant_id' => $tenant['id'],
            'source_id' => $source->id,
            'webhook_url' => url('/api/v1/webhooks/'.$source->token),
        ], 201);
    }

    private function tenantsOf(User $user): Collection
    {
        $teamIds = DB::table('model_has_roles')
            ->where('model_type', $user::class)
            ->where('model_id', $user->id)
            ->pluck('team_id');

        return Tenant::whereIn('id', $teamIds)->get(['id', 'data']);
    }
}
