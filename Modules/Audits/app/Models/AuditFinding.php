<?php

namespace Modules\Audits\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Concerns\NotifiesAssigneeOnChange;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class AuditFinding extends Model
{
    use BelongsToTenant;
    use NotifiesAssigneeOnChange;

    protected const ASSIGNEE_FIELD = 'responsible_id';

    protected const ASSIGNEE_KIND = 'feststellung';

    protected const ASSIGNEE_LABEL = 'Feststellung';

    protected const ASSIGNEE_DUE_FIELD = 'due_at';

    public const SEVERITIES = ['low', 'medium', 'high', 'critical'];

    public const STATUSES = ['open', 'in_progress', 'resolved', 'accepted'];

    protected $attributes = ['severity' => 'medium', 'status' => 'open'];

    protected $fillable = [
        'audit_id', 'title', 'description', 'severity', 'status',
        'due_at', 'responsible_id', 'reminded_at',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'date',
            'reminded_at' => 'datetime',
        ];
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }
}
