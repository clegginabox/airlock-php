<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Queue\Storage\Fifo;

/**
 * Low-level storage operations for FIFO queue semantics.
 *
 * This interface abstracts the underlying storage mechanism (Redis, in-memory, etc.)
 * from the queue logic. Implementations must ensure atomicity where noted.
 */
interface FifoQueueStorageInterface
{
    /**
     * Add identifier to the back of the queue if not already present.
     *
     * If the identifier already exists, returns their current position.
     * Implementations should handle "zombie" detection (in tracking set but
     * missing from queue) by re-adding to the back.
     *
     * @return int 1-indexed position in the queue
     */
    public function addToBack(string $identifier): int;

    /**
     * Remove identifier from the queue entirely.
     */
    public function remove(string $identifier): void;

    /**
     * Get the identifier at the front of the queue without removing it.
     *
     * @return string|null The identifier, or null if queue is empty
     */
    public function peekFront(): ?string;

    /**
     * Remove and return the identifier at the front of the queue.
     *
     * @return string|null The identifier, or null if queue is empty
     */
    public function popFront(): ?string;

    /**
     * Get the 1-indexed position of an identifier in the queue.
     *
     * @return int|null Position (1 = front), or null if not in queue
     */
    public function getPosition(string $identifier): ?int;

    /**
     * Check if an identifier is currently in the queue.
     */
    public function contains(string $identifier): bool;
}
