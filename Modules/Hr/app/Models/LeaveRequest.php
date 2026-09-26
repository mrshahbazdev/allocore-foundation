<?php

namespace Modules\Hr\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Person;
use Modules\Hr\Notifications\LeaveDecided;
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

    protected static function booted(): void
    {
        static::updated(function (LeaveRequest $leave) {
            if (! $leave->wasChanged('status') || ! in_array($leave->status, ['approved', 'rejected'], true)) {
                return;
            }
            $email = $leave->person?->email;
            $actor = request()?->user();
            if ($email && (! $actor || $actor->email !== $email)) {
                User::where('email', $email)->first()?->notify(new LeaveDecided($leave));
            }
        });
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
