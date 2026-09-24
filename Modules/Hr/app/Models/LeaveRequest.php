<?php

namespace Modules\Hr\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Person;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class LeaveRequest extends Model
{
    use BelongsToTenant;

    public const TYPES = ['vacation', 'sick', 'other'];

    public const STATUSES = ['pending', 'approved', 'rejected'];

    protected $attributes = ['status' => 'pending'];

    protected $fillable = [
        'person_id', 'type', 'starts_on', 'ends_on', 'status',
        'approved_by', 'decided_at', 'note',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'decided_at' => 'datetime',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
