<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Event;

use Clegginabox\Airlock\Seal\SealToken;

final readonly class LockReleasedEvent
{
    public function __construct(
        public string $airlock,
        public SealToken $token,
    ) {
    }
}
