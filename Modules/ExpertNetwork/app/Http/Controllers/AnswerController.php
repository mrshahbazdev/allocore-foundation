<?php

namespace Modules\ExpertNetwork\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\ExpertNetwork\Models\Answer;
use Modules\ExpertNetwork\Models\Question;

class AnswerController extends Controller
{
    public function store(Request $request, Question $question)
    {
        $validated = $request->validate([
            'body' => ['required', 'string'],
        ]);

        $validated['answered_by'] = $request->user()?->id;

        $answer = $question->answers()->create($validated);

        if ($question->status === Question::STATUS_OPEN) {
            $question->update(['status' => Question::STATUS_ANSWERED]);
        }

        return response()->json($answer, 201);
    }

    public function accept(Answer $answer)
    {
        $answer->question->answers()->where('id', '!=', $answer->id)->update(['is_accepted' => false]);
        $answer->update(['is_accepted' => true]);
        $answer->question->update(['status' => Question::STATUS_ANSWERED]);

        return $answer->fresh();
    }

    public function destroy(Answer $answer)
    {
        $answer->delete();

        return response()->noContent();
    }
}
