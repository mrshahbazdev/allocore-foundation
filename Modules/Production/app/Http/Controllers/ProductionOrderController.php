<?php

namespace Modules\Production\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Production\Models\ProductionOrder;

class ProductionOrderController extends Controller
{
    public function index(Request $request)
    {
        return ProductionOrder::query()
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->machine_id, fn ($q) => $q->where('machine_id', $request->machine_id))
            ->when($request->overdue === '1', fn ($q) => $q->whereIn('status', ['queued', 'running'])->where('due_at', '<', today()))
            ->with('machine:id,name', 'assignee:id,first_name,last_name')
            ->orderBy('due_at')
            ->paginate();
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        return response()->json(ProductionOrder::create($validated)->append('scrap_pct'), 201);
    }

    public function show(ProductionOrder $productionOrder)
    {
        return $productionOrder->load('machine:id,name', 'assignee:id,first_name,last_name')->append('scrap_pct');
    }

    public function update(Request $request, ProductionOrder $productionOrder)
    {
        $validated = $request->validate($this->rules(sometimes: true));

        if (($validated['status'] ?? null) === 'running' && ! $productionOrder->started_at) {
            $validated['started_at'] = now();
        }
        if (in_array($validated['status'] ?? null, ['done', 'rejected'], true) && ! $productionOrder->finished_at) {
            $validated['finished_at'] = now();
        }

        $productionOrder->update($validated);

        return $productionOrder->append('scrap_pct');
    }

    public function destroy(ProductionOrder $productionOrder)
    {
        $productionOrder->delete();

        return response()->noContent();
    }

    private function rules(bool $sometimes = false): array
    {
        $req = $sometimes ? 'sometimes' : 'required';

        return [
            'order_no' => [$req, 'string', 'max:100'],
            'product' => [$req, 'string', 'max:255'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'machine_id' => ['nullable', 'exists:machines,id'],
            'assigned_to' => ['nullable', 'exists:persons,id'],
            'status' => ['sometimes', Rule::in(ProductionOrder::STATUSES)],
            'scrap_qty' => ['sometimes', 'integer', 'min:0'],
            'due_at' => ['nullable', 'date'],
        ];
    }
}
