<?php

namespace Modules\ExpertNetwork\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\ExpertNetwork\Notifications\AnswerAccepted;
use Modules\ExpertNetwork\Notifications\QuestionAnswered;
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

    protected static function booted(): void
    {
        static::created(function (Answer $answer) {
            $asker = $answer->question?->asker;
            if ($asker && $asker->id !== $answer->answered_by) {
                $asker->notify(new QuestionAnswered($answer));
            }
        });
        static::updated(function (Answer $answer) {
            if ($answer->wasChanged('is_accepted') && $answer->is_accepted) {
                $author = $answer->author;
                $actor = request()?->user();
                if ($author && (! $actor || $actor->id !== $author->id)) {
                    $author->notify(new AnswerAccepted($answer));
                }
            }
        });
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
