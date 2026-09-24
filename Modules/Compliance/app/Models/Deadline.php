<?php

namespace Modules\Compliance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Deadline extends Model
{
    use BelongsToTenant;

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
