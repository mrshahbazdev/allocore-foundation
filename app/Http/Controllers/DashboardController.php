<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        // Ephemeral token so the page can call the tenant-scoped API client-side.
        $token = $request->user()->createToken('dashboard')->plainTextToken;

        return view('dashboard', [
            'tenants' => Tenant::all()->sortBy('name')->values(),
            'apiToken' => $token,
        ]);
    }
}
