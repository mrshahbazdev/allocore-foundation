<?php

namespace Modules\CorporateDev\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Measure extends Model
{
    use BelongsToTenant;

    public const STATUSES = ['open', 'in_progress', 'done', 'cancelled'];

    protected $fillable = [
        'project_id', 'title', 'description', 'status', 'responsible_id', 'due_at',
    ];

    protected function casts(): array
    {
        return ['due_at' => 'date'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }
}
