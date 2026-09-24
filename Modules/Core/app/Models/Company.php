<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Company extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'name',
        'legal_form',
        'street',
        'zip',
        'city',
        'country',
    ];

    public function persons(): HasMany
    {
        return $this->hasMany(Person::class);
    }
}
