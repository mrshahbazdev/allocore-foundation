<?php

namespace Modules\Executive\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class ExecReport extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'title', 'generated_by', 'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}
