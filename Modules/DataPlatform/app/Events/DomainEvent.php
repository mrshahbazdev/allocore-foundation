<?php

namespace Modules\DataPlatform\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class DomainEvent extends ShouldBeStored
{
    public function __construct(
        public string $type,
        public string $tenantId,
        public array $subject,
        public array $payload = [],
    ) {}

    /** meta_data fragment attributing the event to the authenticated user, if any. */
    public static function actorMeta(): array
    {
        $u = auth()->user();

        return $u ? ['actor' => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email]] : [];
    }
}
