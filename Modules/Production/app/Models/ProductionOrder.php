<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Concerns\NotifiesLinkedUserOnCreate;
use Modules\Core\Models\Person;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class ProductionOrder extends Model
{
    use BelongsToTenant;
    use NotifiesLinkedUserOnCreate;

    protected const PERSON_REL = 'assignee';

    protected const PERSON_ID_FIELD = 'assigned_to';

    protected const PERSON_KIND = 'auftrag';

    protected const PERSON_LABEL = 'Auftrag';

    protected const PERSON_DUE_FIELD = 'due_at';

    public const STATUSES = ['queued', 'running', 'done', 'rejected', 'cancelled'];

    protected $fillable = [
        'order_no', 'product', 'quantity', 'machine_id', 'assigned_to',
        'status', 'scrap_qty', 'due_at', 'started_at', 'finished_at',
        'reminded_at',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'date',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'reminded_at' => 'datetime',
        ];
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'assigned_to');
    }

    /** Ausschussquote in %. */
    public function getScrapPctAttribute(): float
    {
        return $this->quantity > 0 ? round($this->scrap_qty / $this->quantity * 100, 2) : 0.0;
    }
}
