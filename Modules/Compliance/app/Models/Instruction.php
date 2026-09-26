<?php

namespace Modules\Compliance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Concerns\NotifiesAssigneeOnChange;
use Modules\Core\Concerns\NotifiesLinkedUserOnCreate;
use Modules\Core\Concerns\NotifiesLinkedUserOnStatus;
use Modules\Core\Models\Person;
use Modules\Documents\Models\Document;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Instruction extends Model
{
    use BelongsToTenant;
    use NotifiesAssigneeOnChange;
    use NotifiesLinkedUserOnCreate;
    use NotifiesLinkedUserOnStatus;

    protected const DONE_FIELD = 'status';

    protected const DONE_VALUE = 'completed';

    protected const DONE_LABEL = 'abgeschlossen';

    protected const ASSIGNEE_FIELD = 'responsible_id';

    protected const ASSIGNEE_KIND = 'unterweisung';

    protected const ASSIGNEE_LABEL = 'Unterweisung';

    protected const ASSIGNEE_DUE_FIELD = 'due_at';

    protected const PERSON_REL = 'person';

    protected const PERSON_KIND = 'unterweisung';

    protected const PERSON_LABEL = 'Unterweisung';

    protected const PERSON_DUE_FIELD = 'due_at';

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
