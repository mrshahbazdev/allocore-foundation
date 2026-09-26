<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Database\Seeders\RoleSeeder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class TokenController extends Controller
{
    /** Erlaubte Token-Abilities: alle <domain>.view/.manage + Sonderrechte (entspricht dem Rollenmodell). */
    public static function allowedAbilities(): array
    {
        $abilities = ['*'];
        foreach (RoleSeeder::DOMAINS as $domain) {
            $abilities[] = $domain.'.view';
            $abilities[] = $domain.'.manage';
        }

        return array_merge($abilities, RoleSeeder::EXTRA_PERMISSIONS);
    }

    /** Erlaubte Token-Abilities (für das Formular). */
    public function abilities()
    {
        return response()->json(self::allowedAbilities());
    }

    /** Eigene API-Token des Nutzers (ohne Token-Wert — der wird nur einmal bei Erstellung ausgegeben). */
    public function index(Request $request)
    {
        return $request->user()->tokens()
            ->select('id', 'name', 'abilities', 'last_used_at', 'expires_at', 'created_at')
            ->orderByDesc('id')
            ->get();
    }

    /** Neuen Personal Access Token ausstellen — Klartext nur in dieser Antwort sichtbar. */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'abilities' => ['nullable', 'array', 'max:50'],
            'abilities.*' => ['string', Rule::in(self::allowedAbilities())],
        ]);

        $expiresAt = isset($validated['expires_in_days'])
            ? now()->addDays((int) $validated['expires_in_days'])
            : null;

        $abilities = $validated['abilities'] ?? ['*'];
        $token = $request->user()->createToken($validated['name'], $abilities, $expiresAt);

        return response()->json([
            'id' => $token->accessToken->id,
            'name' => $validated['name'],
            'token' => $token->plainTextToken,
            'abilities' => $abilities,
            'expires_at' => $expiresAt?->toISOString(),
        ], 201);
    }

    /** Eigenen Token widerrufen. */
    public function destroy(Request $request, string $id)
    {
        $deleted = $request->user()->tokens()->where('id', $id)->delete();

        abort_unless($deleted, 404);

        return response()->json(['status' => 'ok']);
    }
}
