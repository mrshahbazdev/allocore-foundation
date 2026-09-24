<?php

namespace Modules\Finance\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Finance\Models\FinancialReport;

class FinancialReportController extends Controller
{
    public function index(Request $request)
    {
        return FinancialReport::query()
            ->when($request->company_id, fn ($q) => $q->where('company_id', $request->company_id))
            ->when($request->from, fn ($q) => $q->where('period', '>=', $request->from))
            ->when($request->to, fn ($q) => $q->where('period', '<=', $request->to))
            ->with('company:id,name')
            ->orderBy('period')
            ->paginate();
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        return response()->json(FinancialReport::updateOrCreate(
            ['company_id' => $validated['company_id'], 'period' => $validated['period']],
            $validated,
        ), 201);
    }

    public function show(FinancialReport $financialReport)
    {
        return $financialReport->load('company:id,name');
    }

    public function update(Request $request, FinancialReport $financialReport)
    {
        $validated = $request->validate($this->rules(sometimes: true));

        $financialReport->update($validated);

        return $financialReport;
    }

    public function destroy(FinancialReport $financialReport)
    {
        $financialReport->delete();

        return response()->noContent();
    }

    private function rules(bool $sometimes = false): array
    {
        $req = $sometimes ? 'sometimes' : 'required';

        return [
            'company_id' => [$req, 'exists:companies,id'],
            'period' => [$req, 'regex:/^\d{4}-\d{2}$/'],
            'revenue' => ['sometimes', 'numeric'],
            'cashflow' => ['sometimes', 'numeric'],
            'ebitda' => ['sometimes', 'numeric'],
            'liquidity' => ['sometimes', 'numeric'],
        ];
    }
}
