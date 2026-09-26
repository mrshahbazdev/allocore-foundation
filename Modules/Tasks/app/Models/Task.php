<?php

namespace Modules\Tasks\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Tasks\Notifications\TaskAssigned;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Task extends Model
{
    use BelongsToTenant, HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_DONE = 'done';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'title',
        'description',
        'status',
        'assignee_id',
        'created_by',
        'due_at',
        'reminded_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'reminded_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        $notify = function (Task $task) {
            $actor = request()?->user();
            if ($task->assignee_id && $actor && $actor->id !== $task->assignee_id) {
                $task->assignee?->notify(new TaskAssigned($task));
            }
        };
        static::created($notify);
        static::updated(fn (Task $task) => $task->wasChanged('assignee_id') ? $notify($task) : null);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
