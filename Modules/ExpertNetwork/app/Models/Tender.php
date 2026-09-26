<?php

namespace Modules\ExpertNetwork\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Company;
use Modules\ExpertNetwork\Notifications\TenderPublished;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Tender extends Model
{
    use BelongsToTenant;

    public const STATUS_OPEN = 'open';

    public const STATUS_AWARDED = 'awarded';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'title', 'description', 'required_skills', 'budget', 'company_id',
        'created_by', 'status', 'deadline_at', 'reminded_at',
    ];

    protected function casts(): array
    {
        return [
            'required_skills' => 'array',
            'deadline_at' => 'datetime',
            'reminded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Tender $tender) {
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
                ->each(fn (User $u) => $u->notify(new TenderPublished($tender)));
        });
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
