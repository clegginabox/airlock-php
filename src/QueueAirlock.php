<?php

declare(strict_types=1);

namespace Clegginabox\Airlock;

use Clegginabox\Airlock\Notifier\AirlockNotifierInterface;
use Clegginabox\Airlock\Presence\PresenceProviderInterface;
use Clegginabox\Airlock\Queue\EnumerableQueue;
use Clegginabox\Airlock\Queue\QueueInterface;
use Clegginabox\Airlock\Reservation\ReservationStoreInterface;
use Clegginabox\Airlock\Seal\ReleasableSeal;
use Clegginabox\Airlock\Seal\Seal;
use Clegginabox\Airlock\Seal\SealToken;
use Clegginabox\Airlock\Supervisor\AirlockSupervisor;

final readonly class QueueAirlock implements Airlock, ReleasingAirlock, ClaimingAirlock
{
    public function __construct(
        private Seal&ReleasableSeal $seal,
        private QueueInterface $queue,
        private string $topicPrefix = '/waiting-room',
        private ?ReservationStoreInterface $reservations = null,
    ) {
    }

    public function enter(string $identifier, int $priority = 0): EntryResult
    {
        $position = $this->queue->add($identifier, $priority);

        if ($position !== 1) {
            return EntryResult::queued($position, $this->topicFor($identifier));
        }

        $token = $this->seal->tryAcquire();

        if ($token !== null) {
            $this->queue->remove($identifier);
            $this->reservations?->clear($identifier);

            return EntryResult::admitted($token, $this->topicFor($identifier));
        }

        return EntryResult::queued(1, $this->topicFor($identifier));
    }

    public function leave(string $identifier): void
    {
        $this->queue->remove($identifier);
        $this->reservations?->clear($identifier);
    }

    public function release(SealToken $token): void
    {
        $this->seal->release($token);
    }

    public function getPosition(string $identifier): ?int
    {
        return $this->queue->getPosition($identifier);
    }

    public function getTopic(string $identifier): string
    {
        return $this->topicFor($identifier);
    }

    public function claim(string $identifier, string $reservationNonce): ClaimResult
    {
        if ($this->reservations === null) {
            return ClaimResult::missed($this->topicFor($identifier));
        }

        if (!$this->reservations->isReservedFor($identifier, $reservationNonce)) {
            return ClaimResult::missed($this->topicFor($identifier));
        }

        $token = $this->seal->tryAcquire();

        if ($token === null) {
            return ClaimResult::unavailable($this->topicFor($identifier));
        }

        if (!$this->reservations->consume($identifier, $reservationNonce)) {
            $this->seal->release($token);

            return ClaimResult::missed($this->topicFor($identifier));
        }

        $this->queue->remove($identifier);

        return ClaimResult::admitted($token, $this->topicFor($identifier));
    }

    public function getReservationNonce(string $identifier): ?string
    {
        return $this->reservations?->getReservationNonce($identifier);
    }

    public function createSupervisor(
        AirlockNotifierInterface $notifier,
        int $claimWindowSeconds = 10,
        ?PresenceProviderInterface $presenceProvider = null,
    ): AirlockSupervisor {
        if (!$this->queue instanceof EnumerableQueue) {
            throw new \LogicException(sprintf(
                'The queue (%s) does not implement %s.',
                $this->queue::class,
                EnumerableQueue::class,
            ));
        }

        return new AirlockSupervisor(
            queue: $this->queue,
            notifier: $notifier,
            topicPrefix: $this->topicPrefix,
            claimWindowSeconds: $claimWindowSeconds,
            presenceProvider: $presenceProvider,
            reservations: $this->reservations,
            canNotifyCandidate: fn (): bool => $this->canAdmitNow(),
        );
    }

    private function canAdmitNow(): bool
    {
        $token = $this->seal->tryAcquire();

        if ($token === null) {
            return false;
        }

        $this->seal->release($token);

        return true;
    }

    private function topicFor(string $identifier): string
    {
        return rtrim($this->topicPrefix, '/') . '/' . $identifier;
    }
}
