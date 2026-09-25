<?php

namespace Modules\KnowledgeGraph\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class GraphEdge extends Model
{
    use BelongsToTenant;

    protected $fillable = ['from_entity_id', 'to_entity_id', 'relation', 'properties'];

    protected function casts(): array
    {
        return ['properties' => 'array'];
    }

    public function from(): BelongsTo
    {
        return $this->belongsTo(GraphEntity::class, 'from_entity_id');
    }

    public function to(): BelongsTo
    {
        return $this->belongsTo(GraphEntity::class, 'to_entity_id');
    }
}
