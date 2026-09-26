<?php

namespace Modules\Audits\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Audits\Models\Audit;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        return Audit::query()
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->q, fn ($q, $s) => $q->where('title', 'like', '%'.$s.'%'))
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->when($request->company_id, fn ($q) => $q->where('company_id', $request->company_id))
            ->withCount(['findings', 'findings as open_findings_count' => fn ($q) => $q->whereIn('status', ['open', 'in_progress'])])
            ->when($request->responsible_id, fn ($q, $v) => $q->where('responsible_id', $v))
            ->with('company:id,name', 'responsible:id,name')
            ->orderByDesc('starts_on')
            ->when(
                in_array($request->sort, ['starts_on', 'ends_on', 'status', 'title', 'responsible_id', 'created_at'], true),
                fn ($q) => $q->orderBy($request->sort, $request->dir === 'asc' ? 'asc' : 'desc')
            )
            ->paginate(min($request->integer('per_page', 200), 200));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['sometimes', Rule::in(Audit::TYPES)],
            'standard' => ['nullable', 'string', 'max:255'],
            'auditor' => ['nullable', 'string', 'max:255'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'responsible_id' => ['nullable', 'exists:users,id'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'status' => ['sometimes', Rule::in(Audit::STATUSES)],
            'result' => ['nullable', 'string'],
        ]);

        return response()->json(Audit::create($validated), 201);
    }

    public function show(Audit $audit)
    {
        return $audit->load('company:id,name', 'responsible:id,name', 'findings');
    }

    public function update(Request $request, Audit $audit)
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', Rule::in(Audit::TYPES)],
            'standard' => ['nullable', 'string', 'max:255'],
            'auditor' => ['nullable', 'string', 'max:255'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'responsible_id' => ['nullable', 'exists:users,id'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'status' => ['sometimes', Rule::in(Audit::STATUSES)],
            'result' => ['nullable', 'string'],
        ]);

        $audit->update($validated);

        return $audit;
    }

    public function destroy(Audit $audit)
    {
        $audit->delete();

        return response()->noContent();
    }
}
