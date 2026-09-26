<?php

namespace Modules\Compliance\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Compliance\Models\RiskAssessment;

class RiskAssessmentController extends Controller
{
    public function index(Request $request)
    {
        return RiskAssessment::query()
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->q, fn ($q, $s) => $q->where('title', 'like', '%'.$s.'%'))
            ->when($request->risk_level, fn ($q) => $q->where('risk_level', $request->risk_level))
            ->with('assessor:id,first_name,last_name')
            ->paginate(min(request()->integer('per_page', 200), 200));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:255'],
            'hazard' => ['nullable', 'string'],
            'risk_level' => ['sometimes', Rule::in([RiskAssessment::RISK_LOW, RiskAssessment::RISK_MEDIUM, RiskAssessment::RISK_HIGH])],
            'measures' => ['nullable', 'string'],
            'person_id' => ['nullable', 'exists:persons,id'],
            'status' => ['sometimes', Rule::in([RiskAssessment::STATUS_OPEN, RiskAssessment::STATUS_MITIGATED, RiskAssessment::STATUS_ACCEPTED])],
            'review_at' => ['nullable', 'date'],
        ]);

        return response()->json(RiskAssessment::create($validated), 201);
    }

    public function show(RiskAssessment $riskAssessment)
    {
        return $riskAssessment->load('assessor:id,first_name,last_name');
    }

    public function update(Request $request, RiskAssessment $riskAssessment)
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:255'],
            'hazard' => ['nullable', 'string'],
            'risk_level' => ['sometimes', Rule::in([RiskAssessment::RISK_LOW, RiskAssessment::RISK_MEDIUM, RiskAssessment::RISK_HIGH])],
            'measures' => ['nullable', 'string'],
            'person_id' => ['nullable', 'exists:persons,id'],
            'status' => ['sometimes', Rule::in([RiskAssessment::STATUS_OPEN, RiskAssessment::STATUS_MITIGATED, RiskAssessment::STATUS_ACCEPTED])],
            'review_at' => ['nullable', 'date'],
        ]);

        $riskAssessment->update($validated);

        return $riskAssessment;
    }

    public function destroy(RiskAssessment $riskAssessment)
    {
        $riskAssessment->delete();

        return response()->noContent();
    }
}
