<?php

namespace Modules\ExpertNetwork\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\ExpertNetwork\Notifications\QuestionPublished;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Question extends Model
{
    use BelongsToTenant;

    protected static function booted(): void
    {
        static::created(function (Question $question) {
            $actor = request()?->user();
            if (! $actor) {
                return;
            }
            $memberIds = DB::table('model_has_roles')
                ->where('team_id', tenant()->getTenantKey())
                ->where('model_type', User::class)
                ->pluck('model_id');
            User::whereIn('id', $memberIds)
                ->where('id', '!=', $actor->id)
                ->get()
                ->filter(fn (User $u) => $u->hasPermissionTo('experts.manage'))
                ->each(fn (User $u) => $u->notify(new QuestionPublished($question)));
        });
    }

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
