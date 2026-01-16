<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Queue;

use Clegginabox\Airlock\Queue\Storage\Lottery\LotteryQueueStorage;

class LotteryQueue implements QueueInterface
{
    public function __construct(private LotteryQueueStorage $storage)
    {
    }

    public function add(string $identifier, int $priority = 0): int
    {
        return $this->storage->add($identifier, $priority);
    }

    public function remove(string $identifier): void
    {
        $this->storage->remove($identifier);
    }

    public function peek(): ?string
    {
        return $this->storage->peek();
    }

    public function getPosition(string $identifier): ?int
    {
        return $this->storage->getPosition($identifier);
    }
}
