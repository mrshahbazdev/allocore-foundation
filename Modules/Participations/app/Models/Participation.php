<?php

namespace Modules\Participations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Company;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Participation extends Model
{
    use BelongsToTenant;

    public const STATUSES = ['candidate', 'active', 'exited'];

    protected $fillable = [
        'company_id', 'name', 'legal_form', 'stake_pct', 'invested_amount',
        'current_valuation', 'capital_need', 'status', 'acquired_at', 'exited_at',
    ];

    protected function casts(): array
    {
        return [
            'stake_pct' => 'decimal:2',
            'invested_amount' => 'decimal:2',
            'current_valuation' => 'decimal:2',
            'capital_need' => 'decimal:2',
            'acquired_at' => 'date',
            'exited_at' => 'date',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Wertsteigerung in % — null ohne aktuelle Bewertung. */
    public function getValueChangePctAttribute(): ?float
    {
        if ($this->current_valuation === null || (float) $this->invested_amount == 0.0) {
            return null;
        }

        return round((((float) $this->current_valuation - (float) $this->invested_amount) / (float) $this->invested_amount) * 100, 2);
    }
}
