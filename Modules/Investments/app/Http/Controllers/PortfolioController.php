<?php

namespace Modules\Investments\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Investments\Models\Portfolio;

class PortfolioController extends Controller
{
    public function index(Request $request)
    {
        return Portfolio::query()
            ->when($request->q, fn ($q, $s) => $q->where('name', 'like', '%'.$s.'%'))
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->withCount('investments')
            ->paginate(min(request()->integer('per_page', 200), 200));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['sometimes', Rule::in(Portfolio::TYPES)],
            'currency' => ['sometimes', 'string', 'size:3'],
        ]);

        return response()->json(Portfolio::create($validated), 201);
    }

    public function show(Portfolio $portfolio)
    {
        $portfolio->load('investments');
        $portfolio->setAttribute('summary', [
            'cost_basis' => $portfolio->investments->whereNull('disposed_at')->sum(fn ($i) => (float) $i->cost_basis * (float) $i->quantity),
            'current_value' => $portfolio->investments->whereNull('disposed_at')->sum(fn ($i) => (float) ($i->current_value ?? $i->cost_basis) * (float) $i->quantity),
        ]);

        return $portfolio;
    }

    public function update(Request $request, Portfolio $portfolio)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', Rule::in(Portfolio::TYPES)],
            'currency' => ['sometimes', 'string', 'size:3'],
        ]);

        $portfolio->update($validated);

        return $portfolio;
    }

    public function destroy(Portfolio $portfolio)
    {
        $portfolio->delete();

        return response()->noContent();
    }
}
