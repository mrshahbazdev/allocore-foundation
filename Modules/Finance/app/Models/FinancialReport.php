<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Company;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class FinancialReport extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id', 'period', 'revenue', 'cashflow', 'ebitda', 'liquidity',
    ];

    protected function casts(): array
    {
        return [
            'revenue' => 'decimal:2',
            'cashflow' => 'decimal:2',
            'ebitda' => 'decimal:2',
            'liquidity' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
