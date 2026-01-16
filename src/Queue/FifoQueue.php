<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Queue;

use Clegginabox\Airlock\Queue\Storage\Fifo\FifoQueueStorageInterface;

/**
 * FIFO queue implementation that delegates to a storage backend.
 *
 * This is a thin adapter between QueueInterface and QueueStorageInterface,
 * allowing the queue logic to be decoupled from storage specifics.
 */
final readonly class FifoQueue implements QueueInterface
{
    public function __construct(
        private FifoQueueStorageInterface $storage,
    ) {
    }

    public function add(string $identifier, int $priority = 0): int
    {
        // FIFO queue ignores priority - items always go to the back
        return $this->storage->addToBack($identifier);
    }

    public function remove(string $identifier): void
    {
        $this->storage->remove($identifier);
    }

    public function peek(): ?string
    {
        return $this->storage->peekFront();
    }

    public function pop(): ?string
    {
        return $this->storage->popFront();
    }

    public function getPosition(string $identifier): ?int
    {
        return $this->storage->getPosition($identifier);
    }
}
