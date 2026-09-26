<?php

namespace Modules\CorporateDev\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Concerns\NotifiesAssigneeOnChange;
use Modules\Core\Notifications\Assigned;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Measure extends Model
{
    use BelongsToTenant;
    use NotifiesAssigneeOnChange;

    protected const ASSIGNEE_FIELD = 'responsible_id';

    protected const ASSIGNEE_KIND = 'massnahme';

    protected const ASSIGNEE_LABEL = 'Maßnahme';

    protected const ASSIGNEE_DUE_FIELD = 'due_at';

    public const STATUSES = ['open', 'in_progress', 'done', 'cancelled'];

    protected $fillable = [
        'project_id', 'title', 'description', 'status', 'responsible_id', 'due_at', 'reminded_at',
    ];

    protected function casts(): array
    {
        return ['due_at' => 'date', 'reminded_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updated(function (Measure $measure) {
            if (! $measure->wasChanged('status') || $measure->status !== 'done') {
                return;
            }
            $owner = $measure->project?->owner;
            $actor = request()?->user();
            if (! $owner || (int) $owner->id === (int) $actor?->id) {
                return;
            }
            $owner->notify(new Assigned(
                kind: 'massnahme',
                entityId: (string) $measure->getKey(),
                title: 'Maßnahme abgeschlossen: '.$measure->title,
            ));
        });
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
