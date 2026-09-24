<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Machine extends Model
{
    use BelongsToTenant;

    public const TYPES = ['milling', 'printer3d', 'scanner', 'sinter', 'other'];

    public const STATUSES = ['active', 'maintenance', 'retired'];

    protected $fillable = ['name', 'type', 'capacity_units_per_day', 'status'];

    public function orders(): HasMany
    {
        return $this->hasMany(ProductionOrder::class);
    }
}
