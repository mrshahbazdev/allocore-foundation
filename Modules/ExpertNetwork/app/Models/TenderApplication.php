<?php

namespace Modules\ExpertNetwork\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\ExpertNetwork\Notifications\ApplicationDecided;
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

    protected static function booted(): void
    {
        static::updated(function (TenderApplication $application) {
            if (! $application->wasChanged('status')
                || ! in_array($application->status, [self::STATUS_AWARDED, self::STATUS_SHORTLISTED, self::STATUS_REJECTED], true)) {
                return;
            }
            $email = $application->expertProfile?->person?->email;
            if ($email) {
                User::where('email', $email)->first()?->notify(new ApplicationDecided($application));
            }
        });
    }

    public function tender()
    {
        return $this->belongsTo(Tender::class);
    }

    public function expertProfile()
    {
        return $this->belongsTo(ExpertProfile::class);
    }
}
