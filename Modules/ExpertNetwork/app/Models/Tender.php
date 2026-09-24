<?php

namespace Modules\ExpertNetwork\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\Company;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Tender extends Model
{
    use BelongsToTenant;

    public const STATUS_OPEN = 'open';

    public const STATUS_AWARDED = 'awarded';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'title', 'description', 'required_skills', 'budget', 'company_id',
        'created_by', 'status', 'deadline_at',
    ];

    protected function casts(): array
    {
        return [
            'required_skills' => 'array',
            'deadline_at' => 'datetime',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function applications()
    {
        return $this->hasMany(TenderApplication::class);
    }
}
