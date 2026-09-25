<?php

namespace Modules\Compliance\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Compliance\Models\Deadline;

class DeadlineController extends Controller
{
    public function index(Request $request)
    {
        return Deadline::query()
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->with('responsible:id,name')
            ->paginate(min(request()->integer('per_page', 200), 200));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'subject_type' => ['nullable', 'string', 'max:255'],
            'subject_id' => ['nullable', 'integer'],
            'responsible_id' => ['nullable', 'exists:users,id'],
            'status' => ['sometimes', Rule::in([Deadline::STATUS_OPEN, Deadline::STATUS_COMPLETED])],
            'due_at' => ['required', 'date'],
        ]);

        return response()->json(Deadline::create($validated), 201);
    }

    public function show(Deadline $deadline)
    {
        return $deadline->load('responsible:id,name', 'subject');
    }

    public function update(Request $request, Deadline $deadline)
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'subject_type' => ['nullable', 'string', 'max:255'],
            'subject_id' => ['nullable', 'integer'],
            'responsible_id' => ['nullable', 'exists:users,id'],
            'status' => ['sometimes', Rule::in([Deadline::STATUS_OPEN, Deadline::STATUS_COMPLETED])],
            'due_at' => ['sometimes', 'date'],
        ]);

        if (($validated['status'] ?? null) === Deadline::STATUS_COMPLETED) {
            $validated['completed_at'] = now();
        }

        $deadline->update($validated);

        return $deadline;
    }

    public function destroy(Deadline $deadline)
    {
        $deadline->delete();

        return response()->noContent();
    }
}
