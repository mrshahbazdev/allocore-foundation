<?php

namespace Modules\Ai\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class AiAnalysis extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'kind', 'provider', 'status', 'summary', 'findings', 'meta',
    ];

    protected $casts = [
        'findings' => 'array',
        'meta' => 'array',
    ];
}
