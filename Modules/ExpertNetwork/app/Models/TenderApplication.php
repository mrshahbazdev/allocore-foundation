<?php

namespace Modules\ExpertNetwork\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class TenderApplication extends Model
{
    use BelongsToTenant;

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_SHORTLISTED = 'shortlisted';

    public const STATUS_AWARDED = 'awarded';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'tender_id', 'expert_profile_id', 'proposal', 'price', 'status',
    ];

    public function tender()
    {
        return $this->belongsTo(Tender::class);
    }

    public function expertProfile()
    {
        return $this->belongsTo(ExpertProfile::class);
    }
}
