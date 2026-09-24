<?php

namespace Modules\ExpertNetwork\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Question extends Model
{
    use BelongsToTenant;

    public const STATUS_OPEN = 'open';

    public const STATUS_ANSWERED = 'answered';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'title', 'body', 'category', 'asked_by', 'expert_profile_id', 'status',
    ];

    public function asker()
    {
        return $this->belongsTo(User::class, 'asked_by');
    }

    public function expertProfile()
    {
        return $this->belongsTo(ExpertProfile::class);
    }

    public function answers()
    {
        return $this->hasMany(Answer::class);
    }
}
