<?php

namespace Modules\ExpertNetwork\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\ExpertNetwork\Models\ExpertProfile;

class ExpertProfileController extends Controller
{
    public function index(Request $request)
    {
        return ExpertProfile::query()
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->q, fn ($q, $s) => $q->where('headline', 'like', '%'.$s.'%'))
            ->with('person:id,first_name,last_name,email')
            ->paginate(min(request()->integer('per_page', 200), 200));
    }

    public function match(Request $request)
    {
        $request->validate(['skills' => ['required', 'array'], 'skills.*' => ['string']]);

        $wanted = collect($request->skills)->map(fn ($s) => mb_strtolower($s));

        return ExpertProfile::query()
            ->where('status', ExpertProfile::STATUS_ACTIVE)
            ->with('person:id,first_name,last_name,email')
            ->get()
            ->map(function (ExpertProfile $profile) use ($wanted) {
                $profile->match_score = $wanted->intersect(collect($profile->skills ?? [])->map(fn ($s) => mb_strtolower($s)))->count();

                return $profile;
            })
            ->where('match_score', '>', 0)
            ->sortByDesc('match_score')
            ->values();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'person_id' => ['required', 'exists:persons,id'],
            'headline' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'skills' => ['nullable', 'array'],
            'skills.*' => ['string', 'max:100'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::in([ExpertProfile::STATUS_ACTIVE, ExpertProfile::STATUS_INACTIVE])],
        ]);

        return response()->json(ExpertProfile::create($validated), 201);
    }

    public function show(ExpertProfile $expertProfile)
    {
        return $expertProfile->load('person:id,first_name,last_name,email');
    }

    public function update(Request $request, ExpertProfile $expertProfile)
    {
        $validated = $request->validate([
            'headline' => ['sometimes', 'nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'skills' => ['nullable', 'array'],
            'skills.*' => ['string', 'max:100'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::in([ExpertProfile::STATUS_ACTIVE, ExpertProfile::STATUS_INACTIVE])],
        ]);

        $expertProfile->update($validated);

        return $expertProfile;
    }

    public function destroy(ExpertProfile $expertProfile)
    {
        $expertProfile->delete();

        return response()->noContent();
    }
}
