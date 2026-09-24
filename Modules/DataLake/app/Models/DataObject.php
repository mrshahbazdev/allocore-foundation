<?php

namespace Modules\DataLake\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class DataObject extends Model
{
    use BelongsToTenant;

    public const CATEGORIES = ['pdf', 'image', 'contract', 'cad', 'production-data', 'other'];

    protected $fillable = [
        'name', 'category', 'mime_type', 'size_bytes', 'disk', 'path', 'uploaded_by',
    ];
}
