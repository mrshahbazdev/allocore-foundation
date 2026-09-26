<?php

namespace Modules\Hr\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Core\Notifications\Assigned;
use Modules\Hr\Models\LeaveRequest;

class LeaveRequestController extends Controller
{
    public function index(Request $request)
    {
        return LeaveRequest::query()
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->when($request->active === '1', fn ($q) => $q->where('status', 'approved')
                ->where('starts_on', '<=', today())->where('ends_on', '>=', today()))
            ->with('person:id,first_name,last_name')
            ->orderByDesc('starts_on')
            ->paginate(min(request()->integer('per_page', 200), 200));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'person_id' => ['required', 'exists:persons,id'],
            'type' => ['required', Rule::in(LeaveRequest::TYPES)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'note' => ['nullable', 'string'],
        ]);

        $leaveRequest = LeaveRequest::create($validated);

        $personName = trim(($leaveRequest->person->first_name ?? '').' '.($leaveRequest->person->last_name ?? ''));
        $memberIds = DB::table('model_has_roles')
            ->where('team_id', tenant()->getTenantKey())
            ->where('model_type', User::class)
            ->pluck('model_id');
        User::whereIn('id', $memberIds)
            ->get()
            ->filter(fn (User $u) => (int) $u->id !== (int) $request->user()->id && $u->hasPermissionTo('hr.manage'))
            ->each(fn (User $u) => $u->notify(new Assigned(
                'urlaub',
                $leaveRequest->id,
                'Neuer '.$leaveRequest->type.'-Antrag von '.$personName.' ('.$leaveRequest->starts_on->format('d.m.Y').'–'.$leaveRequest->ends_on->format('d.m.Y').')',
                $leaveRequest->starts_on->toDateString(),
            )));

        return response()->json($leaveRequest, 201);
    }

    public function show(LeaveRequest $leaveRequest)
    {
        return $leaveRequest->load('person:id,first_name,last_name', 'approver:id,name');
    }

    public function update(Request $request, LeaveRequest $leaveRequest)
    {
        $validated = $request->validate([
            'person_id' => ['sometimes', 'exists:persons,id'],
            'type' => ['sometimes', Rule::in(LeaveRequest::TYPES)],
            'starts_on' => ['sometimes', 'date'],
            'ends_on' => ['sometimes', 'date', 'after_or_equal:starts_on'],
            'status' => ['sometimes', Rule::in(LeaveRequest::STATUSES)],
            'note' => ['nullable', 'string'],
        ]);

        if (in_array($validated['status'] ?? null, ['approved', 'rejected'], true)) {
            $validated['approved_by'] = $request->user()->id;
            $validated['decided_at'] = now();
        }

        $leaveRequest->update($validated);

        return $leaveRequest;
    }

    public function destroy(LeaveRequest $leaveRequest)
    {
        $leaveRequest->delete();

        return response()->noContent();
    }
}
