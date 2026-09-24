<?php

namespace Modules\ExpertNetwork\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\Person;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class ExpertProfile extends Model
{
    use BelongsToTenant;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'person_id', 'headline', 'bio', 'skills', 'hourly_rate', 'status',
    ];

    protected function casts(): array
    {
        return ['skills' => 'array'];
    }

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function applications()
    {
        return $this->hasMany(TenderApplication::class);
    }
}
