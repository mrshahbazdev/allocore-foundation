<?php

namespace Modules\DataPlatform\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class IntegrationSource extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'kind', 'token', 'active', 'last_received_at'];

    protected $casts = [
        'active' => 'boolean',
        'last_received_at' => 'datetime',
    ];

    protected $hidden = [];

    public static function generateToken(): string
    {
        return 'wh_'.bin2hex(random_bytes(24));
    }
}
