<?php

namespace Modules\Production\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Production\Models\Machine;

class MachineController extends Controller
{
    public function index(Request $request)
    {
        return Machine::query()
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->withCount(['orders', 'orders as open_orders_count' => fn ($q) => $q->whereIn('status', ['queued', 'running'])])
            ->paginate(min(request()->integer('per_page', 200), 200));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['sometimes', Rule::in(Machine::TYPES)],
            'capacity_units_per_day' => ['sometimes', 'integer', 'min:1'],
            'status' => ['sometimes', Rule::in(Machine::STATUSES)],
        ]);

        return response()->json(Machine::create($validated), 201);
    }

    public function show(Machine $machine)
    {
        return $machine->load(['orders' => fn ($q) => $q->whereIn('status', ['queued', 'running'])->orderBy('due_at')]);
    }

    public function update(Request $request, Machine $machine)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', Rule::in(Machine::TYPES)],
            'capacity_units_per_day' => ['sometimes', 'integer', 'min:1'],
            'status' => ['sometimes', Rule::in(Machine::STATUSES)],
        ]);

        $machine->update($validated);

        return $machine;
    }

    public function destroy(Machine $machine)
    {
        $machine->delete();

        return response()->noContent();
    }
}
