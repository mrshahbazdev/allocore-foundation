<?php

namespace Modules\CorporateDev\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\CorporateDev\Models\Project;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        return Project::query()
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->strategy_id, fn ($q) => $q->where('strategy_id', $request->strategy_id))
            ->with('owner:id,name')
            ->withCount('measures')
            ->paginate();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'strategy_id' => ['nullable', 'exists:strategies,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(Project::STATUSES)],
            'progress' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'owner_id' => ['nullable', 'exists:users,id'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        return response()->json(Project::create($validated), 201);
    }

    public function show(Project $project)
    {
        return $project->load('strategy:id,name', 'owner:id,name', 'measures');
    }

    public function update(Request $request, Project $project)
    {
        $validated = $request->validate([
            'strategy_id' => ['nullable', 'exists:strategies,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(Project::STATUSES)],
            'progress' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'owner_id' => ['nullable', 'exists:users,id'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $project->update($validated);

        return $project;
    }

    public function destroy(Project $project)
    {
        $project->delete();

        return response()->noContent();
    }
}
