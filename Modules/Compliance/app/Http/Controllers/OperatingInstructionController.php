<?php

namespace Modules\Compliance\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Compliance\Models\OperatingInstruction;

class OperatingInstructionController extends Controller
{
    public function index(Request $request)
    {
        return OperatingInstruction::query()
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->q, fn ($q, $s) => $q->where('title', 'like', '%'.$s.'%'))
            ->paginate(min(request()->integer('per_page', 200), 200));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'version' => ['sometimes', 'integer', 'min:1'],
            'document_id' => ['nullable', 'exists:documents,id'],
            'valid_from' => ['nullable', 'date'],
            'status' => ['sometimes', Rule::in([OperatingInstruction::STATUS_DRAFT, OperatingInstruction::STATUS_ACTIVE, OperatingInstruction::STATUS_ARCHIVED])],
        ]);

        return response()->json(OperatingInstruction::create($validated), 201);
    }

    public function show(OperatingInstruction $operatingInstruction)
    {
        return $operatingInstruction->load('document:id,title');
    }

    public function update(Request $request, OperatingInstruction $operatingInstruction)
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'version' => ['sometimes', 'integer', 'min:1'],
            'document_id' => ['nullable', 'exists:documents,id'],
            'valid_from' => ['nullable', 'date'],
            'status' => ['sometimes', Rule::in([OperatingInstruction::STATUS_DRAFT, OperatingInstruction::STATUS_ACTIVE, OperatingInstruction::STATUS_ARCHIVED])],
        ]);

        $operatingInstruction->update($validated);

        return $operatingInstruction;
    }

    public function destroy(OperatingInstruction $operatingInstruction)
    {
        $operatingInstruction->delete();

        return response()->noContent();
    }
}
