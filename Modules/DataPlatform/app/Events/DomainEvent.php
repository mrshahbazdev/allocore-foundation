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
}
