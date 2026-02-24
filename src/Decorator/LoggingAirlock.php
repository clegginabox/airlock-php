<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Decorator;

use Clegginabox\Airlock\Airlock;
use Clegginabox\Airlock\ClaimingAirlock;
use Clegginabox\Airlock\ClaimResult;
use Clegginabox\Airlock\EntryResult;
use Clegginabox\Airlock\RefreshingAirlock;
use Clegginabox\Airlock\ReleasingAirlock;
use Clegginabox\Airlock\Seal\SealToken;
use Psr\Log\LoggerInterface;

final readonly class LoggingAirlock implements Airlock, ReleasingAirlock, RefreshingAirlock, ClaimingAirlock
{
    public function __construct(
        private Airlock $inner,
        private LoggerInterface $logger,
    ) {
    }

    public function enter(string $identifier, int $priority = 0): EntryResult
    {
        $this->logger->debug('Attempting to enter airlock', [
            'identifier' => $identifier,
            'priority' => $priority,
        ]);

        $result = $this->inner->enter($identifier, $priority);

        if ($result->isAdmitted()) {
            $this->logger->info('Admitted to airlock', [
                'identifier' => $identifier,
                'token' => (string) $result->getToken(),
            ]);
        } else {
            $this->logger->info('Queued in airlock', [
                'identifier' => $identifier,
                'position' => $result->getPosition(),
            ]);
        }

        return $result;
    }

    public function leave(string $identifier): void
    {
        $this->inner->leave($identifier);

        $this->logger->info('Left airlock queue', [
            'identifier' => $identifier,
        ]);
    }

    public function release(SealToken $token): void
    {
        if (! $this->inner instanceof ReleasingAirlock) {
            throw new \LogicException(sprintf(
                'The inner airlock (%s) does not implement %s.',
                $this->inner::class,
                ReleasingAirlock::class,
            ));
        }

        $this->inner->release($token);

        $this->logger->info('Released airlock lock', [
            'token' => (string) $token,
        ]);
    }

    public function refresh(SealToken $token, ?float $ttlInSeconds = null): ?SealToken
    {
        if (! $this->inner instanceof RefreshingAirlock) {
            throw new \LogicException(sprintf(
                'The inner airlock (%s) does not implement %s.',
                $this->inner::class,
                RefreshingAirlock::class,
            ));
        }

        $newToken = $this->inner->refresh($token, $ttlInSeconds);

        $this->logger->info('Refreshed airlock lease', [
            'old_token' => (string) $token,
            'new_token' => $newToken !== null ? (string) $newToken : null,
            'ttl' => $ttlInSeconds,
        ]);

        return $newToken;
    }

    public function getPosition(string $identifier): ?int
    {
        return $this->inner->getPosition($identifier);
    }

    public function getTopic(string $identifier): string
    {
        return $this->inner->getTopic($identifier);
    }

    public function claim(string $identifier, string $reservationNonce): ClaimResult
    {
        if (!$this->inner instanceof ClaimingAirlock) {
            throw new \LogicException(sprintf(
                'The inner airlock (%s) does not implement %s.',
                $this->inner::class,
                ClaimingAirlock::class,
            ));
        }

        $this->logger->debug('Attempting to claim reservation', [
            'identifier' => $identifier,
        ]);

        $result = $this->inner->claim($identifier, $reservationNonce);

        $this->logger->info('Claim attempt completed', [
            'identifier' => $identifier,
            'status' => $result->getStatus(),
            'token' => $result->getToken() !== null ? (string) $result->getToken() : null,
        ]);

        return $result;
    }

    public function getReservationNonce(string $identifier): ?string
    {
        if (!$this->inner instanceof ClaimingAirlock) {
            throw new \LogicException(sprintf(
                'The inner airlock (%s) does not implement %s.',
                $this->inner::class,
                ClaimingAirlock::class,
            ));
        }

        return $this->inner->getReservationNonce($identifier);
    }
}
