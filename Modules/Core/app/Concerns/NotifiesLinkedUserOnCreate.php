<?php

namespace Modules\Core\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Notifications\Assigned;

/**
 * Sends a database notification to the user whose email matches the linked
 * person record (static::PERSON_REL) whenever a record is created or the
 * linked person (static::PERSON_ID_FIELD) changes — the person being
 * instructed/inspected/assessed is informed, not the manager.
 */
trait NotifiesLinkedUserOnCreate
{
    protected static function bootNotifiesLinkedUserOnCreate(): void
    {
        $notify = function (Model $model) {
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
                title: static::PERSON_LABEL.' für Sie: '.($model->title ?? $model->name ?? $model->order_no ?? 'Eintrag'),
                dueAt: isset($model->{static::PERSON_DUE_FIELD})
                    ? $model->{static::PERSON_DUE_FIELD}?->format('d.m.Y')
                    : null,
            ));
        };

        $personIdField = defined('static::PERSON_ID_FIELD') ? static::PERSON_ID_FIELD : 'person_id';

        static::created($notify);
        static::updated(fn (Model $model) => $model->wasChanged($personIdField) ? $notify($model) : null);
    }
}
