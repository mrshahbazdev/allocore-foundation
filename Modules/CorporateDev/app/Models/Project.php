<?php

namespace Modules\CorporateDev\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Concerns\NotifiesAssigneeOnChange;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Project extends Model
{
    use BelongsToTenant;
    use NotifiesAssigneeOnChange;

    protected const ASSIGNEE_FIELD = 'owner_id';

    protected const ASSIGNEE_KIND = 'projekt';

    protected const ASSIGNEE_LABEL = 'Projekt';

    protected const ASSIGNEE_DUE_FIELD = 'ends_at';

    public const STATUSES = ['planned', 'active', 'on_hold', 'done', 'cancelled'];

    protected $fillable = [
        'strategy_id', 'name', 'description', 'status', 'progress',
        'owner_id', 'starts_at', 'ends_at', 'reminded_at',
    ];

    protected function casts(): array
    {
        return ['starts_at' => 'date', 'ends_at' => 'date', 'progress' => 'integer'];
    }

    public function strategy(): BelongsTo
    {
        return $this->belongsTo(Strategy::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function measures(): HasMany
    {
        return $this->hasMany(Measure::class);
    }
}
