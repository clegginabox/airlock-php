<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Decorator;

use Clegginabox\Airlock\Airlock;
use Clegginabox\Airlock\ClaimingAirlock;
use Clegginabox\Airlock\ClaimResult;
use Clegginabox\Airlock\EntryResult;
use Clegginabox\Airlock\Event\EntryAdmittedEvent;
use Clegginabox\Airlock\Event\EntryQueuedEvent;
use Clegginabox\Airlock\Event\LeaseRefreshedEvent;
use Clegginabox\Airlock\Event\LockReleasedEvent;
use Clegginabox\Airlock\Event\UserLeftEvent;
use Clegginabox\Airlock\RefreshingAirlock;
use Clegginabox\Airlock\ReleasingAirlock;
use Clegginabox\Airlock\Seal\SealToken;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class EventDispatchingAirlock implements Airlock, ReleasingAirlock, RefreshingAirlock, ClaimingAirlock
{
    public function __construct(
        private Airlock $inner,
        private EventDispatcherInterface $dispatcher,
        private string $airlockIdentifier = 'default'
    ) {
    }

    public function enter(string $identifier, int $priority = 0): EntryResult
    {
        $result = $this->inner->enter($identifier, $priority);

        if ($result->isAdmitted()) {
            /** @var SealToken $token */
            $token = $result->getToken();
            $this->dispatcher->dispatch(new EntryAdmittedEvent(
                $this->airlockIdentifier,
                $identifier,
                $token,
                $result->getTopic(),
            ));
        } else {
            /** @var int $position */
            $position = $result->getPosition();
            $this->dispatcher->dispatch(new EntryQueuedEvent(
                $this->airlockIdentifier,
                $identifier,
                $position,
                $result->getTopic(),
            ));
        }

        return $result;
    }

    public function leave(string $identifier): void
    {
        $this->inner->leave($identifier);

        $this->dispatcher->dispatch(new UserLeftEvent($this->airlockIdentifier, $identifier));
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

        $this->dispatcher->dispatch(new LockReleasedEvent($this->airlockIdentifier, $token));
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

        $this->dispatcher->dispatch(new LeaseRefreshedEvent($this->airlockIdentifier, $token, $newToken, $ttlInSeconds));

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

        $result = $this->inner->claim($identifier, $reservationNonce);

        if (!$result->isAdmitted()) {
            return $result;
        }

        /** @var SealToken $token */
        $token = $result->getToken();
        $this->dispatcher->dispatch(new EntryAdmittedEvent(
            $this->airlockIdentifier,
            $identifier,
            $token,
            $result->getTopic(),
        ));

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
