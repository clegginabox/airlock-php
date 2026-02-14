<?php

declare(strict_types=1);

namespace Clegginabox\Airlock;

use Clegginabox\Airlock\Seal\ReleasableSeal;
use Clegginabox\Airlock\Seal\Seal;
use Clegginabox\Airlock\Seal\SealToken;

/**
 * A waiting room implementation that uses polling to determine admission.
 * The first user to acquire the semaphore is admitted.
 */
final readonly class OpportunisticAirlock implements Airlock, ReleasingAirlock
{
    public function __construct(
        private Seal&ReleasableSeal $seal,
    ) {
    }

    public function enter(string $identifier, int $priority = 0): EntryResult
    {
        $token = $this->seal->tryAcquire();
        if ($token !== null) {
            return EntryResult::admitted($token, '');
        }

        return EntryResult::queued(-1, '');
    }

    public function release(SealToken $token): void
    {
        $this->seal->release($token);
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
