<?php

namespace Modules\KnowledgeGraph\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class GraphEntity extends Model
{
    use BelongsToTenant;

    protected $fillable = ['type', 'name', 'subject_type', 'subject_id', 'properties'];

    protected function casts(): array
    {
        return ['properties' => 'array'];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function outgoing(): HasMany
    {
        return $this->hasMany(GraphEdge::class, 'from_entity_id');
    }

    public function incoming(): HasMany
    {
        return $this->hasMany(GraphEdge::class, 'to_entity_id');
    }
}
