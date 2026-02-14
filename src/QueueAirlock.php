<?php

declare(strict_types=1);

namespace Clegginabox\Airlock;

use Clegginabox\Airlock\Notifier\AirlockNotifierInterface;
use Clegginabox\Airlock\Queue\QueueInterface;
use Clegginabox\Airlock\Seal\ReleasableSeal;
use Clegginabox\Airlock\Seal\Seal;
use Clegginabox\Airlock\Seal\SealToken;

final readonly class QueueAirlock implements Airlock, ReleasingAirlock
{
    public function __construct(
        private Seal&ReleasableSeal $seal,
        private QueueInterface $queue,
        private AirlockNotifierInterface $notifier,
        private string $topicPrefix = '/waiting-room',
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
            return EntryResult::admitted($token, $this->topicFor($identifier));
        }

        return EntryResult::queued(1, $this->topicFor($identifier));
    }

    public function leave(string $identifier): void
    {
        $this->queue->remove($identifier);
    }

    public function release(SealToken $token): void
    {
        $this->seal->release($token);

        $nextPassenger = $this->queue->peek();

        if ($nextPassenger === null) {
            return;
        }

        $this->notifier->notify($nextPassenger, $this->topicFor($nextPassenger));
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
