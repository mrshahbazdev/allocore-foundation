<?php

namespace Modules\Compliance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\Person;
use Modules\Documents\Models\Document;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Instruction extends Model
{
    use BelongsToTenant;

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'title', 'content', 'document_id', 'person_id', 'responsible_id',
        'status', 'interval_months', 'due_at', 'completed_at', 'reminded_at',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'reminded_at' => 'datetime',
        ];
    }

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function responsible()
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function document()
    {
        return $this->belongsTo(Document::class);
    }
}
