<?php

namespace Modules\CorporateDev\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\CorporateDev\Models\Strategy;

class StrategyController extends Controller
{
    public function index(Request $request)
    {
        return Strategy::query()
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->q, fn ($q, $s) => $q->where('name', 'like', '%'.$s.'%'))
            ->withCount('projects')
            ->paginate(min(request()->integer('per_page', 200), 200));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(Strategy::STATUSES)],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        return response()->json(Strategy::create($validated), 201);
    }

    public function show(Strategy $strategy)
    {
        return $strategy->load('projects:id,strategy_id,name,status,progress');
    }

    public function update(Request $request, Strategy $strategy)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(Strategy::STATUSES)],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $strategy->update($validated);

        return $strategy;
    }

    public function destroy(Strategy $strategy)
    {
        $strategy->delete();

        return response()->noContent();
    }
}
