<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Queue;

/**
 * Manages the waiting queue for users who cannot immediately enter the airlock.
 *
 * Users are added to the queue when capacity is full and removed when they
 * either gain access or voluntarily leave.
 */
interface QueueInterface
{
    /**
     * Add a passenger to the back of the queue.
     * Returns their new position (1 = front).
     */
    public function add(string $identifier, int $priority = 0): int;

    /**
     * Remove a specific passenger from the queue (e.g. they entered or gave up).
     */
    public function remove(string $identifier): void;

    /**
     * Look at who is currently at the front (Position 1), without removing them.
     * Returns the passenger ID, or null if queue is empty.
     */
    public function peek(): ?string;

    /**
     * Get the current position of a passenger.
     * Returns null if they are not in the queue.
     */
    public function getPosition(string $identifier): ?int;
}
