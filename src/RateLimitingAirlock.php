<?php

declare(strict_types=1);

namespace Clegginabox\Airlock;

use Clegginabox\Airlock\Seal\RateLimitingSeal;

class RateLimitingAirlock implements Airlock
{
    public function __construct(private RateLimitingSeal $seal)
    {
    }

    public function enter(string $identifier, int $priority = 0): EntryResult
    {
        $token = $this->seal->tryAcquire();
        if ($token !== null) {
            return EntryResult::admitted($token, '');
        }

        return EntryResult::queued(-1, '');
    }

    public function leave(string $identifier): void
    {
        // Nothing to do here
    }

    public function getPosition(string $identifier): ?int
    {
        return null;
    }

    public function getTopic(string $identifier): string
    {
        return '';
    }
}
