<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Event;

use Clegginabox\Airlock\Seal\SealToken;

final readonly class LeaseRefreshedEvent
{
    public function __construct(
        public string $airlock,
        public SealToken $oldToken,
        public ?SealToken $newToken,
        public ?float $ttlInSeconds,
    ) {
    }
}
