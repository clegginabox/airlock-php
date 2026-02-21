<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Event;

final readonly class EntryQueuedEvent
{
    public function __construct(
        public string $airlock,
        public string $identifier,
        public int $position,
        public string $topic,
    ) {
    }
}
