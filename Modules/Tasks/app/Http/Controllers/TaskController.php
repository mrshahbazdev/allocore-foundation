<?php

namespace Modules\Tasks\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Core\Notifications\Assigned;
use Modules\Tasks\Models\Task;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        return Task::query()
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->with('assignee:id,name,email')
            ->paginate(min(request()->integer('per_page', 200), 200));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assignee_id' => ['nullable', 'exists:users,id'],
            'due_at' => ['nullable', 'date'],
            'status' => ['sometimes', Rule::in([Task::STATUS_OPEN, Task::STATUS_IN_PROGRESS, Task::STATUS_DONE, Task::STATUS_CANCELLED])],
        ]);

        $validated['created_by'] = $request->user()?->id;

        return response()->json(Task::create($validated), 201);
    }

    public function show(Task $task)
    {
        return $task->load('assignee:id,name,email', 'creator:id,name,email');
    }

    public function update(Request $request, Task $task)
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assignee_id' => ['nullable', 'exists:users,id'],
            'due_at' => ['nullable', 'date'],
            'status' => ['sometimes', Rule::in([Task::STATUS_OPEN, Task::STATUS_IN_PROGRESS, Task::STATUS_DONE, Task::STATUS_CANCELLED])],
        ]);

        if (($validated['status'] ?? null) === Task::STATUS_DONE) {
            $validated['completed_at'] = now();
        }

        $wasOpen = $task->status !== Task::STATUS_DONE;
        $task->update($validated);

        if ($wasOpen && $task->status === Task::STATUS_DONE
            && $task->created_by && (int) $task->created_by !== (int) $request->user()->id) {
            User::find($task->created_by)?->notify(new Assigned(
                'aufgabe',
                $task->id,
                'Aufgabe erledigt: '.$task->title,
            ));
        }

        return $task;
    }

    public function destroy(Task $task)
    {
        $task->delete();

        return response()->noContent();
    }
}
