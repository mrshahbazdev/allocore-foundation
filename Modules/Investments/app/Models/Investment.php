<?php

namespace Modules\Investments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Investment extends Model
{
    use BelongsToTenant;

    public const ASSET_CLASSES = ['equity', 'bond', 'fund', 'real_estate', 'participation', 'cash', 'other'];

    protected $fillable = [
        'portfolio_id', 'name', 'asset_class', 'quantity',
        'cost_basis', 'current_value', 'valued_at', 'acquired_at', 'disposed_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:6',
            'cost_basis' => 'decimal:2',
            'current_value' => 'decimal:2',
            'valued_at' => 'date',
            'acquired_at' => 'date',
            'disposed_at' => 'date',
        ];
    }

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }

    /** Rendite in % — null wenn keine Bewertung vorliegt. */
    public function getReturnPctAttribute(): ?float
    {
        if ($this->current_value === null || (float) $this->cost_basis == 0.0) {
            return null;
        }

        return round((((float) $this->current_value - (float) $this->cost_basis) / (float) $this->cost_basis) * 100, 2);
    }
}
