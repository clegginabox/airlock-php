<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Event;

use Clegginabox\Airlock\Seal\SealToken;

final readonly class EntryAdmittedEvent
{
    public function __construct(
        public string $airlock,
        public string $identifier,
        public SealToken $token,
        public string $topic,
    ) {
    }
}
