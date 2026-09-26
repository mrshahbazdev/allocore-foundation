<?php

namespace Modules\Core\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Notifications\Assigned;

/**
 * Sends a database notification to the user referenced by
 * static::ASSIGNEE_FIELD whenever a record is created or the
 * field changes — unless the actor assigns themselves or the
 * write happens outside an authenticated HTTP request.
 */
trait NotifiesAssigneeOnChange
{
    protected static function bootNotifiesAssigneeOnChange(): void
    {
        $notify = function (Model $model) {
            $actor = request()?->user();
            $assigneeId = $model->{static::ASSIGNEE_FIELD};
            if (! $assigneeId || ! $actor || (int) $actor->id === (int) $assigneeId) {
                return;
            }

            User::find($assigneeId)?->notify(new Assigned(
                kind: static::ASSIGNEE_KIND,
                entityId: (string) $model->getKey(),
                title: static::ASSIGNEE_LABEL.' zugewiesen: '.($model->title ?? $model->name ?? 'Eintrag'),
                dueAt: isset($model->{static::ASSIGNEE_DUE_FIELD})
                    ? $model->{static::ASSIGNEE_DUE_FIELD}?->format('d.m.Y')
                    : null,
            ));
        };

        static::created($notify);
        static::updated(fn (Model $model) => $model->wasChanged(static::ASSIGNEE_FIELD) ? $notify($model) : null);
    }
}
