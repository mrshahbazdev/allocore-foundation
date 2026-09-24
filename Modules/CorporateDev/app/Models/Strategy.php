<?php

namespace Modules\CorporateDev\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Strategy extends Model
{
    use BelongsToTenant;

    public const STATUSES = ['draft', 'active', 'archived'];

    protected $fillable = ['name', 'description', 'status', 'starts_at', 'ends_at'];

    protected function casts(): array
    {
        return ['starts_at' => 'date', 'ends_at' => 'date'];
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
