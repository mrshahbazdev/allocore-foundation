<?php

namespace Modules\Core\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Notifications\Assigned;

/**
 * Sends a database notification to the user whose email matches the linked
 * person record (static::PERSON_REL) whenever a record is created — the
 * person being instructed/inspected/assessed is informed, not the manager.
 */
trait NotifiesLinkedUserOnCreate
{
    protected static function bootNotifiesLinkedUserOnCreate(): void
    {
        static::created(function (Model $model) {
            $actor = request()?->user();
            $person = $model->{static::PERSON_REL};
            if (! $actor || ! $person?->email) {
                return;
            }
            $user = User::where('email', $person->email)->first();
            if (! $user || (int) $user->id === (int) $actor->id) {
                return;
            }
            $user->notify(new Assigned(
                kind: static::PERSON_KIND,
                entityId: (string) $model->getKey(),
                title: static::PERSON_LABEL.' für Sie: '.($model->title ?? $model->name ?? 'Eintrag'),
                dueAt: isset($model->{static::PERSON_DUE_FIELD})
                    ? $model->{static::PERSON_DUE_FIELD}?->format('d.m.Y')
                    : null,
            ));
        });
    }
}
