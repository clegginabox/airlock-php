<?php

declare(strict_types=1);

namespace Clegginabox\Airlock;

use Clegginabox\Airlock\Seal\RefreshableSeal;
use Clegginabox\Airlock\Seal\ReleasableSeal;
use Clegginabox\Airlock\Seal\SealToken;
use RuntimeException;

/**
 * A waiting room implementation that uses polling to determine admission.
 * The first user to acquire the semaphore is admitted.
 */
final readonly class OpportunisticAirlock implements AirlockInterface
{
    public function __construct(
        private ReleasableSeal&RefreshableSeal $seal,
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

    public function leave(string $identifier): void
    {
        // Nothing to do here
    }

    public function release(SealToken $token): void
    {
        $this->seal->release($token);
    }

    public function refresh(SealToken $token, ?float $ttlInSeconds = null): ?SealToken
    {
        return $this->seal->refresh($token, $ttlInSeconds);
    }

    public function withAdmitted(
        string $identifier,
        callable $fn,
        ?float $timeoutSeconds = null,
        float $pollIntervalSeconds = 0.5,
    ): mixed {
        $deadline = $timeoutSeconds !== null ? microtime(true) + $timeoutSeconds : null;

        while (true) {
            $result = $this->enter($identifier);

            if ($result->isAdmitted()) {
                $token = $result->getToken();
                assert($token !== null);

                try {
                    return $fn($token);
                } finally {
                    $this->release($token);
                }
            }

            if ($deadline !== null && microtime(true) >= $deadline) {
                $this->leave($identifier);
                throw new RuntimeException('Timed out waiting for admission');
            }

            usleep((int) ($pollIntervalSeconds * 1_000_000));
        }
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
