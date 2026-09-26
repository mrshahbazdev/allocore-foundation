<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TokenController extends Controller
{
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
        ]);

        $expiresAt = isset($validated['expires_in_days'])
            ? now()->addDays((int) $validated['expires_in_days'])
            : null;

        $token = $request->user()->createToken($validated['name'], ['*'], $expiresAt);

        return response()->json([
            'id' => $token->accessToken->id,
            'name' => $validated['name'],
            'token' => $token->plainTextToken,
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
