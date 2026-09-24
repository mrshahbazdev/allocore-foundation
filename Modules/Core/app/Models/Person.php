<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * HR entity — deliberately separate from App\Models\User (ADR-001):
 * a Person needs no login; a consultant has a login without employee status.
 */
class Person extends Model
{
    use BelongsToTenant, HasFactory;

    public const TYPE_EMPLOYEE = 'employee';

    public const TYPE_CONTACT = 'contact';

    public const TYPE_CONSULTANT = 'consultant';

    protected $table = 'persons';

    protected $fillable = [
        'company_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'type',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
