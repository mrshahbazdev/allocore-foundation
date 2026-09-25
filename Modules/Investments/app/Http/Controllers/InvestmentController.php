<?php

namespace Modules\Investments\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Investments\Models\Investment;

class InvestmentController extends Controller
{
    public function index(Request $request)
    {
        return Investment::query()
            ->when($request->portfolio_id, fn ($q) => $q->where('portfolio_id', $request->portfolio_id))
            ->when($request->asset_class, fn ($q) => $q->where('asset_class', $request->asset_class))
            ->when($request->active === '1', fn ($q) => $q->whereNull('disposed_at'))
            ->with('portfolio:id,name')
            ->paginate(min(request()->integer('per_page', 200), 200));
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        return response()->json(Investment::create($validated)->append('return_pct'), 201);
    }

    public function show(Investment $investment)
    {
        return $investment->load('portfolio:id,name')->append('return_pct');
    }

    public function update(Request $request, Investment $investment)
    {
        $validated = $request->validate($this->rules(sometimes: true));

        $investment->update($validated);

        return $investment->append('return_pct');
    }

    public function destroy(Investment $investment)
    {
        $investment->delete();

        return response()->noContent();
    }

    private function rules(bool $sometimes = false): array
    {
        $req = $sometimes ? 'sometimes' : 'required';

        return [
            'portfolio_id' => [$req, 'exists:portfolios,id'],
            'name' => [$req, 'string', 'max:255'],
            'asset_class' => ['sometimes', Rule::in(Investment::ASSET_CLASSES)],
            'quantity' => ['sometimes', 'numeric', 'min:0'],
            'cost_basis' => [$sometimes ? 'sometimes' : 'required', 'numeric', 'min:0'],
            'current_value' => ['nullable', 'numeric', 'min:0'],
            'valued_at' => ['nullable', 'date'],
            'acquired_at' => ['nullable', 'date'],
            'disposed_at' => ['nullable', 'date'],
        ];
    }
}
