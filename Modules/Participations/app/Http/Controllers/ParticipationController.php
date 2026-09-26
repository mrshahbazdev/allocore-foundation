<?php

namespace Modules\Participations\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Participations\Models\Participation;

class ParticipationController extends Controller
{
    public function index(Request $request)
    {
        return Participation::query()
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->q, fn ($q, $s) => $q->where('name', 'like', '%'.$s.'%'))
            ->when($request->company_id, fn ($q, $v) => $q->where('company_id', $v))
            ->with('company:id,name')
            ->paginate(min(request()->integer('per_page', 200), 200));
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        return response()->json(Participation::create($validated)->append('value_change_pct'), 201);
    }

    public function show(Participation $participation)
    {
        return $participation->load('company:id,name')->append('value_change_pct');
    }

    public function update(Request $request, Participation $participation)
    {
        $validated = $request->validate($this->rules(sometimes: true));

        $participation->update($validated);

        return $participation->append('value_change_pct');
    }

    public function destroy(Participation $participation)
    {
        $participation->delete();

        return response()->noContent();
    }

    private function rules(bool $sometimes = false): array
    {
        $req = $sometimes ? 'sometimes' : 'required';

        return [
            'company_id' => ['nullable', 'exists:companies,id'],
            'name' => [$req, 'string', 'max:255'],
            'legal_form' => ['nullable', 'string', 'max:50'],
            'stake_pct' => [$sometimes ? 'sometimes' : 'required', 'numeric', 'min:0', 'max:100'],
            'invested_amount' => ['sometimes', 'numeric', 'min:0'],
            'current_valuation' => ['nullable', 'numeric', 'min:0'],
            'capital_need' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::in(Participation::STATUSES)],
            'acquired_at' => ['nullable', 'date'],
            'exited_at' => ['nullable', 'date'],
        ];
    }
}
