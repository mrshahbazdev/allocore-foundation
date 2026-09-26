<?php

namespace Modules\ExpertNetwork\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\ExpertNetwork\Models\Question;

class QuestionController extends Controller
{
    public function index(Request $request)
    {
        return Question::query()
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->q, fn ($q, $s) => $q->where('title', 'like', '%'.$s.'%'))
            ->when($request->category, fn ($q) => $q->where('category', $request->category))
            ->with('asker:id,name', 'expertProfile:id,headline')
            ->withCount('answers')
            ->paginate(min(request()->integer('per_page', 200), 200));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
            'expert_profile_id' => ['nullable', 'exists:expert_profiles,id'],
        ]);

        $validated['asked_by'] = $request->user()?->id;

        return response()->json(Question::create($validated), 201);
    }

    public function show(Question $question)
    {
        return $question->load('asker:id,name', 'expertProfile:id,headline', 'answers.author:id,name');
    }

    public function update(Request $request, Question $question)
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
            'expert_profile_id' => ['nullable', 'exists:expert_profiles,id'],
            'status' => ['sometimes', Rule::in([Question::STATUS_OPEN, Question::STATUS_ANSWERED, Question::STATUS_CLOSED])],
        ]);

        $question->update($validated);

        return $question;
    }

    public function destroy(Question $question)
    {
        $question->delete();

        return response()->noContent();
    }
}
