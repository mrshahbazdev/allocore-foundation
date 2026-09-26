<?php

namespace Modules\Core\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Notifications\Assigned;

/**
 * Sends a database notification to the user whose email matches the linked
 * person record (static::PERSON_REL) when static::DONE_FIELD transitions to
 * static::DONE_VALUE — e.g. "Prüfung abgeschlossen", "Unterweisung erledigt".
 */
trait NotifiesLinkedUserOnStatus
{
    protected static function bootNotifiesLinkedUserOnStatus(): void
    {
        static::updated(function (Model $model) {
            $field = defined('static::DONE_FIELD') ? static::DONE_FIELD : 'status';
            if (! $model->wasChanged($field) || $model->{$field} !== static::DONE_VALUE) {
                return;
            }
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
                title: static::PERSON_LABEL.' '.static::DONE_LABEL.': '.($model->title ?? $model->name ?? $model->order_no ?? 'Eintrag'),
            ));
        });
    }
}
