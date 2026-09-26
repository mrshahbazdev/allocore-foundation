<?php

namespace Modules\Audits\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Audits\Models\Audit;
use Modules\Audits\Models\AuditFinding;
use Modules\Core\Notifications\Assigned;

class AuditFindingController extends Controller
{
    public function index(Request $request)
    {
        return AuditFinding::query()
            ->when($request->audit_id, fn ($q) => $q->where('audit_id', $request->audit_id))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->severity, fn ($q) => $q->where('severity', $request->severity))
            ->with('audit:id,title', 'responsible:id,name')
            ->orderBy('due_at')
            ->paginate(min($request->integer('per_page', 200), 200));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'audit_id' => ['required', 'exists:audits,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'severity' => ['sometimes', Rule::in(AuditFinding::SEVERITIES)],
            'status' => ['sometimes', Rule::in(AuditFinding::STATUSES)],
            'due_at' => ['nullable', 'date'],
            'responsible_id' => ['nullable', 'exists:users,id'],
        ]);

        $audit = Audit::find($validated['audit_id']);
        if (! $audit) {
            abort(404);
        }

        $finding = AuditFinding::create($validated);

        if ($audit->responsible_id && (int) $audit->responsible_id !== (int) $request->user()->id) {
            User::find($audit->responsible_id)?->notify(new Assigned(
                'feststellung',
                $finding->id,
                'Neue Feststellung in Audit "'.$audit->title.'": '.$finding->title,
                $finding->due_at,
            ));
        }

        return response()->json($finding, 201);
    }

    public function show(AuditFinding $auditFinding)
    {
        return $auditFinding->load('audit:id,title', 'responsible:id,name');
    }

    public function update(Request $request, AuditFinding $auditFinding)
    {
        $validated = $request->validate([
            'audit_id' => ['sometimes', 'exists:audits,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'severity' => ['sometimes', Rule::in(AuditFinding::SEVERITIES)],
            'status' => ['sometimes', Rule::in(AuditFinding::STATUSES)],
            'due_at' => ['nullable', 'date'],
            'responsible_id' => ['nullable', 'exists:users,id'],
        ]);

        $auditFinding->update($validated);

        return $auditFinding;
    }

    public function destroy(AuditFinding $auditFinding)
    {
        $auditFinding->delete();

        return response()->noContent();
    }
}
