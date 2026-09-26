<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function __invoke(Request $request, string $section = 'dashboard')
    {
        // Workspace-Token werden bei jedem Seitenaufruf neu ausgestellt —
        // alte widerrufen, damit sich keine Token ansammeln.
        $request->user()->tokens()->where('name', 'workspace')->delete();
        $token = $request->user()->createToken('workspace')->plainTextToken;

        return view('workspace', [
            'tenants' => Tenant::all()->sortBy('name')->values(),
            'apiToken' => $token,
            'section' => $section,
            'user' => $request->user(),
        ]);
    }
}
