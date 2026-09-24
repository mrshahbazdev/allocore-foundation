<?php

namespace Modules\Compliance\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Documents\Models\Document;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class OperatingInstruction extends Model
{
    use BelongsToTenant;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'title', 'content', 'version', 'document_id', 'valid_from', 'status',
    ];

    protected function casts(): array
    {
        return ['valid_from' => 'date'];
    }

    public function document()
    {
        return $this->belongsTo(Document::class);
    }
}
