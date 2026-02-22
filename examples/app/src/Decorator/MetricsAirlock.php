<?php

declare(strict_types=1);

namespace App\Decorator;

use Clegginabox\Airlock\Airlock;
use Clegginabox\Airlock\ClaimingAirlock;
use Clegginabox\Airlock\ClaimResult;
use Clegginabox\Airlock\EntryResult;
use Clegginabox\Airlock\RefreshingAirlock;
use Clegginabox\Airlock\ReleasingAirlock;
use Clegginabox\Airlock\Seal\SealToken;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Spiral\RoadRunner\Metrics\MetricsInterface;

final readonly class MetricsAirlock implements Airlock, ReleasingAirlock, RefreshingAirlock, ClaimingAirlock
{
    private LoggerInterface $logger;

    public function __construct(
        private Airlock $inner,
        private MetricsInterface $metrics,
        private string $airlockIdentifier = 'default',
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function enter(string $identifier, int $priority = 0): EntryResult
    {
        $start = hrtime(true);

        $result = $this->inner->enter($identifier, $priority);

        $this->recordSafely(function () use ($start, $result): void {
            $durationSeconds = (hrtime(true) - $start) / 1e9;
            $this->metrics->observe('airlock_entry_duration_seconds', $durationSeconds, [$this->airlockIdentifier]);

            $resultLabel = $result->isAdmitted() ? 'admitted' : 'queued';
            $this->metrics->add('airlock_entries_total', 1, [$this->airlockIdentifier, $resultLabel]);
        });

        return $result;
    }

    public function leave(string $identifier): void
    {
        $this->inner->leave($identifier);

        $this->recordSafely(function (): void {
            $this->metrics->add('airlock_leaves_total', 1, [$this->airlockIdentifier]);
        });
    }

    public function release(SealToken $token): void
    {
        if (!$this->inner instanceof ReleasingAirlock) {
            throw new \LogicException(sprintf(
                'The inner airlock (%s) does not implement %s.',
                $this->inner::class,
                ReleasingAirlock::class,
            ));
        }

        $this->inner->release($token);

        $this->recordSafely(function (): void {
            $this->metrics->add('airlock_releases_total', 1, [$this->airlockIdentifier]);
        });
    }

    public function refresh(SealToken $token, ?float $ttlInSeconds = null): ?SealToken
    {
        if (!$this->inner instanceof RefreshingAirlock) {
            throw new \LogicException(sprintf(
                'The inner airlock (%s) does not implement %s.',
                $this->inner::class,
                RefreshingAirlock::class,
            ));
        }

        $newToken = $this->inner->refresh($token, $ttlInSeconds);

        $this->recordSafely(function () use ($newToken): void {
            $resultLabel = $newToken !== null ? 'refreshed' : 'expired';
            $this->metrics->add('airlock_refreshes_total', 1, [$this->airlockIdentifier, $resultLabel]);
        });

        return $newToken;
    }

    /**
     * Record metrics without breaking the functional chain.
     *
     * Metrics are observational — a failure to record should never
     * prevent the airlock from functioning (e.g. when RoadRunner
     * RPC is unavailable in a CLI worker context).
     */
    private function recordSafely(callable $record): void
    {
        try {
            $record();
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to record airlock metric', [
                'airlock' => $this->airlockIdentifier,
                'error' => $e->getMessage(),
            ]);
        }
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

        $result = $this->inner->claim($identifier, $reservationNonce);

        $this->recordSafely(function () use ($result): void {
            $this->metrics->add('airlock_claims_total', 1, [$this->airlockIdentifier, $result->getStatus()]);
        });

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
