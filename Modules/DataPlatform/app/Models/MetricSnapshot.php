<?php

namespace Modules\DataPlatform\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class MetricSnapshot extends Model
{
    use BelongsToTenant;

    protected $fillable = ['metric', 'value', 'captured_on', 'tenant_id'];

    protected function casts(): array
    {
        return ['captured_on' => 'date', 'value' => 'decimal:4'];
    }
}
