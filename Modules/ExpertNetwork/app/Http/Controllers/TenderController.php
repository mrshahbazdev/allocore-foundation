<?php

namespace Modules\ExpertNetwork\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\ExpertNetwork\Models\Tender;

class TenderController extends Controller
{
    public function index(Request $request)
    {
        return Tender::query()
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->with('company:id,name', 'creator:id,name')
            ->withCount('applications')
            ->paginate();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'required_skills' => ['nullable', 'array'],
            'required_skills.*' => ['string', 'max:100'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'status' => ['sometimes', Rule::in([Tender::STATUS_OPEN, Tender::STATUS_AWARDED, Tender::STATUS_CLOSED])],
            'deadline_at' => ['nullable', 'date'],
        ]);

        $validated['created_by'] = $request->user()?->id;

        return response()->json(Tender::create($validated), 201);
    }

    public function show(Tender $tender)
    {
        return $tender->load('company:id,name', 'creator:id,name', 'applications.expertProfile.person:id,first_name,last_name');
    }

    public function update(Request $request, Tender $tender)
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'required_skills' => ['nullable', 'array'],
            'required_skills.*' => ['string', 'max:100'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'status' => ['sometimes', Rule::in([Tender::STATUS_OPEN, Tender::STATUS_AWARDED, Tender::STATUS_CLOSED])],
            'deadline_at' => ['nullable', 'date'],
        ]);

        $tender->update($validated);

        return $tender;
    }

    public function destroy(Tender $tender)
    {
        $tender->delete();

        return response()->noContent();
    }
}
