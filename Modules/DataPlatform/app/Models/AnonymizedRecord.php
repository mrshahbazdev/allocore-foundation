<?php

namespace Modules\DataPlatform\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * Pseudonymized twin of a source record (user, person, …) for analytics and the
 * KI-Coach — payload carries no direct identifiers (names, e-mail, phone).
 */
class AnonymizedRecord extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'source_type', 'pseudonym', 'payload', 'synced_at'];

    protected $casts = [
        'payload' => 'array',
        'synced_at' => 'datetime',
    ];

    public static function pseudonymFor(string $type, string|int $id): string
    {
        return hash_hmac('sha256', $type.':'.$id, (string) config('app.key'));
    }
}
