<?php

namespace Modules\ExpertNetwork\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Answer extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'question_id', 'answered_by', 'body', 'is_accepted',
    ];

    protected function casts(): array
    {
        return ['is_accepted' => 'boolean'];
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'answered_by');
    }
}
