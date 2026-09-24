<?php

namespace Modules\Investments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Portfolio extends Model
{
    use BelongsToTenant;

    public const TYPES = ['securities', 'participations', 'real_assets', 'mixed'];

    protected $fillable = ['name', 'type', 'currency'];

    public function investments(): HasMany
    {
        return $this->hasMany(Investment::class);
    }
}
