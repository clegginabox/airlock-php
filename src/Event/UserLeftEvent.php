<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Event;

final readonly class UserLeftEvent
{
    public function __construct(
        public string $airlock,
        public string $identifier,
    ) {
    }
}
