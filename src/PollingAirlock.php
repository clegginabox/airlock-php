<?php

declare(strict_types=1);

namespace Clegginabox\Airlock;

use Clegginabox\Airlock\Seal\SealInterface;

/**
 * A waiting room implementation that uses polling to determine admission.
 * The first user to acquire the semaphore is admitted.
 */
class PollingAirlock implements AirlockInterface
{
    public function __construct(
        private readonly SealInterface $seal,
    ) {
    }

    public function enter(string $identifier): EntryResult
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

    public function release(string $token): void
    {
        $this->seal->release($token);
    }

    public function refresh(string $token, ?float $ttlInSeconds = null): ?string
    {
        return $this->seal->refresh($token, $ttlInSeconds);
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
