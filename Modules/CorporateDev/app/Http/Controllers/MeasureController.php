<?php

namespace Modules\CorporateDev\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\CorporateDev\Models\Measure;

class MeasureController extends Controller
{
    public function index(Request $request)
    {
        return Measure::query()
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->project_id, fn ($q) => $q->where('project_id', $request->project_id))
            ->with('responsible:id,name')
            ->paginate();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(Measure::STATUSES)],
            'responsible_id' => ['nullable', 'exists:users,id'],
            'due_at' => ['nullable', 'date'],
        ]);

        return response()->json(Measure::create($validated), 201);
    }

    public function show(Measure $measure)
    {
        return $measure->load('project:id,name', 'responsible:id,name');
    }

    public function update(Request $request, Measure $measure)
    {
        $validated = $request->validate([
            'project_id' => ['sometimes', 'exists:projects,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(Measure::STATUSES)],
            'responsible_id' => ['nullable', 'exists:users,id'],
            'due_at' => ['nullable', 'date'],
        ]);

        $measure->update($validated);

        return $measure;
    }

    public function destroy(Measure $measure)
    {
        $measure->delete();

        return response()->noContent();
    }
}
