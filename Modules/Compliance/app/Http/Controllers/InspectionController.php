<?php

namespace Modules\Compliance\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Compliance\Models\Inspection;

class InspectionController extends Controller
{
    public function index(Request $request)
    {
        return Inspection::query()
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->q, fn ($q, $s) => $q->where('title', 'like', '%'.$s.'%'))
            ->when($request->boolean('overdue'), fn ($q) => $q->where('status', 'scheduled')->where('scheduled_at', '<', now()))
            ->when($request->boolean('due_soon'), fn ($q) => $q->where('status', 'scheduled')->whereBetween('scheduled_at', [now(), now()->addDays(7)]))
            ->when($request->result, fn ($q) => $q->where('result', $request->result))
            ->when($request->person_id, fn ($q, $v) => $q->where('person_id', $v))
            ->with('person:id,first_name,last_name', 'responsible:id,name')
            ->paginate(min(request()->integer('per_page', 200), 200));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'person_id' => ['nullable', 'exists:persons,id'],
            'responsible_id' => ['nullable', 'exists:users,id'],
            'status' => ['sometimes', Rule::in([Inspection::STATUS_SCHEDULED, Inspection::STATUS_COMPLETED, Inspection::STATUS_CANCELLED])],
            'result' => ['nullable', Rule::in([Inspection::RESULT_PASS, Inspection::RESULT_CONDITIONAL, Inspection::RESULT_FAIL])],
            'notes' => ['nullable', 'string'],
            'scheduled_at' => ['nullable', 'date'],
        ]);

        return response()->json(Inspection::create($validated), 201);
    }

    public function show(Inspection $inspection)
    {
        return $inspection->load('person:id,first_name,last_name', 'responsible:id,name');
    }

    public function update(Request $request, Inspection $inspection)
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'person_id' => ['nullable', 'exists:persons,id'],
            'responsible_id' => ['nullable', 'exists:users,id'],
            'status' => ['sometimes', Rule::in([Inspection::STATUS_SCHEDULED, Inspection::STATUS_COMPLETED, Inspection::STATUS_CANCELLED])],
            'result' => ['nullable', Rule::in([Inspection::RESULT_PASS, Inspection::RESULT_CONDITIONAL, Inspection::RESULT_FAIL])],
            'notes' => ['nullable', 'string'],
            'scheduled_at' => ['nullable', 'date'],
        ]);

        if (($validated['status'] ?? null) === Inspection::STATUS_COMPLETED) {
            $validated['completed_at'] = now();
        }

        $inspection->update($validated);

        return $inspection;
    }

    public function destroy(Inspection $inspection)
    {
        $inspection->delete();

        return response()->noContent();
    }
}
