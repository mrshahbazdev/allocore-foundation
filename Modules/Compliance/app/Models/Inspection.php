<?php

namespace Modules\Compliance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Concerns\NotifiesAssigneeOnChange;
use Modules\Core\Models\Person;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Inspection extends Model
{
    use BelongsToTenant;
    use NotifiesAssigneeOnChange;

    protected const ASSIGNEE_FIELD = 'responsible_id';

    protected const ASSIGNEE_KIND = 'pruefung';

    protected const ASSIGNEE_LABEL = 'Prüfung';

    protected const ASSIGNEE_DUE_FIELD = 'scheduled_at';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const RESULT_PASS = 'pass';

    public const RESULT_CONDITIONAL = 'conditional';

    public const RESULT_FAIL = 'fail';

    protected $fillable = [
        'title', 'type', 'subject', 'person_id', 'responsible_id',
        'status', 'result', 'notes', 'scheduled_at', 'completed_at', 'reminded_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
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
}
