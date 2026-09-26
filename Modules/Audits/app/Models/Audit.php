<?php

namespace Modules\Audits\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Concerns\NotifiesAssigneeOnChange;
use Modules\Core\Models\Company;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Audit extends Model
{
    use BelongsToTenant;
    use NotifiesAssigneeOnChange;

    protected const ASSIGNEE_FIELD = 'responsible_id';

    protected const ASSIGNEE_KIND = 'audit';

    protected const ASSIGNEE_LABEL = 'Audit';

    protected const ASSIGNEE_DUE_FIELD = 'starts_on';

    public const TYPES = ['internal', 'external'];

    public const STATUSES = ['planned', 'in_progress', 'done', 'cancelled'];

    protected $attributes = ['type' => 'internal', 'status' => 'planned'];

    protected $fillable = [
        'title', 'type', 'standard', 'auditor', 'company_id',
        'responsible_id', 'starts_on', 'ends_on', 'status', 'result', 'reminded_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function findings(): HasMany
    {
        return $this->hasMany(AuditFinding::class);
    }
}
