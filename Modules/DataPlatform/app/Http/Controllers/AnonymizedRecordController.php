<?php

namespace Modules\DataPlatform\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\DataPlatform\Models\AnonymizedRecord;

class AnonymizedRecordController extends Controller
{
    /** Pseudonymized twins of the current tenant — no PII in payloads. */
    public function index(Request $request)
    {
        $perPage = min(max((int) $request->per_page, 1), 200);

        return AnonymizedRecord::query()
            ->select('id', 'source_type', 'pseudonym', 'payload', 'synced_at')
            ->where('tenant_id', tenant()->getTenantKey())
            ->when($request->source_type, fn ($q) => $q->where('source_type', $request->source_type))
            ->orderByDesc('synced_at')
            ->paginate($perPage);
    }
}
