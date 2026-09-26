<?php

namespace Modules\Compliance\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Compliance\Models\Instruction;

class InstructionController extends Controller
{
    public function index(Request $request)
    {
        return Instruction::query()
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->q, fn ($q, $s) => $q->where('title', 'like', '%'.$s.'%'))
            ->with('person:id,first_name,last_name', 'responsible:id,name')
            ->paginate(min(request()->integer('per_page', 200), 200));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'document_id' => ['nullable', 'exists:documents,id'],
            'person_id' => ['nullable', 'exists:persons,id'],
            'responsible_id' => ['nullable', 'exists:users,id'],
            'status' => ['sometimes', Rule::in([Instruction::STATUS_PENDING, Instruction::STATUS_COMPLETED])],
            'interval_months' => ['nullable', 'integer', 'min:0', 'max:120'],
            'due_at' => ['nullable', 'date'],
        ]);

        return response()->json(Instruction::create($validated), 201);
    }

    public function show(Instruction $instruction)
    {
        return $instruction->load('person:id,first_name,last_name', 'responsible:id,name', 'document:id,title');
    }

    public function update(Request $request, Instruction $instruction)
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'document_id' => ['nullable', 'exists:documents,id'],
            'person_id' => ['nullable', 'exists:persons,id'],
            'responsible_id' => ['nullable', 'exists:users,id'],
            'status' => ['sometimes', Rule::in([Instruction::STATUS_PENDING, Instruction::STATUS_COMPLETED])],
            'interval_months' => ['nullable', 'integer', 'min:0', 'max:120'],
            'due_at' => ['nullable', 'date'],
        ]);

        if (($validated['status'] ?? null) === Instruction::STATUS_COMPLETED) {
            $validated['completed_at'] = now();
        }

        $instruction->update($validated);

        return $instruction;
    }

    public function destroy(Instruction $instruction)
    {
        $instruction->delete();

        return response()->noContent();
    }
}
