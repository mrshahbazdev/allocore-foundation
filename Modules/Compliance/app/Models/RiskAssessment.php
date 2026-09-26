<?php

namespace Modules\Compliance\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\Person;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class RiskAssessment extends Model
{
    use BelongsToTenant;

    public const RISK_LOW = 'low';

    public const RISK_MEDIUM = 'medium';

    public const RISK_HIGH = 'high';

    public const STATUS_OPEN = 'open';

    public const STATUS_MITIGATED = 'mitigated';

    public const STATUS_ACCEPTED = 'accepted';

    protected $fillable = [
        'title', 'area', 'hazard', 'risk_level', 'measures',
        'person_id', 'status', 'review_at', 'reminded_at',
    ];

    protected function casts(): array
    {
        return ['review_at' => 'datetime'];
    }

    public function assessor()
    {
        return $this->belongsTo(Person::class, 'person_id');
    }
}
