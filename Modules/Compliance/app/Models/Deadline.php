<?php

namespace Modules\Compliance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Concerns\NotifiesAssigneeOnChange;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Deadline extends Model
{
    use BelongsToTenant;
    use NotifiesAssigneeOnChange;

    protected const ASSIGNEE_FIELD = 'responsible_id';

    protected const ASSIGNEE_KIND = 'frist';

    protected const ASSIGNEE_LABEL = 'Frist';

    protected const ASSIGNEE_DUE_FIELD = 'due_at';

    public const STATUS_OPEN = 'open';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'title', 'description', 'subject_type', 'subject_id', 'responsible_id',
        'status', 'due_at', 'completed_at', 'reminded_at',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'reminded_at' => 'datetime',
        ];
    }

    public function subject()
    {
        return $this->morphTo();
    }

    public function responsible()
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }
}
