<?php

declare(strict_types=1);

namespace Clegginabox\Airlock;

use Clegginabox\Airlock\Notifier\AirlockNotifierInterface;
use Clegginabox\Airlock\Queue\QueueInterface;
use Clegginabox\Airlock\Seal\SealInterface;

final readonly class QueueAirlock implements AirlockInterface
{
    public function __construct(
        private SealInterface $seal,
        private QueueInterface $queue,
        private AirlockNotifierInterface $notifier,
        private string $topicPrefix = '/waiting-room',
    ) {
    }

    public function enter(string $identifier): EntryResult
    {
        // 1. SAFETY: ALWAYS join the queue first.
        // This ensures your position is secured before you check the door.
        // (If already in queue, this just returns current position)
        $position = $this->queue->add($identifier);

        // 2. Are we at the front of the line? (Position 1)
        if ($position > 1) {
            // No? Wait your turn.
            return EntryResult::queued($position, $this->topicFor($identifier));
        }

        // 3. We are at the front! Try to break the seal.
        $token = $this->seal->tryAcquire();

        if ($token !== null) {
            // Success! We are admitted.
            // NOW we remove ourselves from the queue.
            $this->queue->remove($identifier);
            return EntryResult::admitted($token, $this->topicFor($identifier));
        }

        // 4. We are at front (#1), but the room is full.
        // We stay in the queue at position 1 and wait for a notification.
        return EntryResult::queued(1, $this->topicFor($identifier));
    }

    public function leave(string $identifier): void
    {
        $this->queue->remove($identifier);
    }

    public function release(string $token): void
    {
        $this->seal->release($token);

        $nextPassenger = $this->queue->peek();

        if ($nextPassenger === null) {
            return;
        }

        $this->notifier->notify($nextPassenger, $this->topicFor($nextPassenger));
    }

    public function refresh(string $token, ?float $ttlInSeconds = null): ?string
    {
        return $this->seal->refresh($token, $ttlInSeconds);
    }

    public function getPosition(string $identifier): ?int
    {
        return $this->queue->getPosition($identifier);
    }

    public function getTopic(string $identifier): string
    {
        return $this->topicFor($identifier);
    }

    private function topicFor(string $identifier): string
    {
        return rtrim($this->topicPrefix, '/') . '/' . $identifier;
    }
}
