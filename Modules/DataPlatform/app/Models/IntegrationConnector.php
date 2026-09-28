<?php

namespace Modules\DataPlatform\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class IntegrationConnector extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'url', 'headers', 'interval_minutes', 'last_run_at', 'last_status', 'active'];

    protected $casts = [
        'headers' => 'array',
        'interval_minutes' => 'integer',
        'last_run_at' => 'datetime',
        'active' => 'boolean',
    ];

    public function due(): bool
    {
        return $this->active
            && (! $this->last_run_at || $this->last_run_at->addMinutes($this->interval_minutes)->isPast());
    }
}
