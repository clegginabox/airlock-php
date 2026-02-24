<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Queue;

/**
 * A queue that can enumerate all its members.
 *
 * Extends QueueInterface with the ability to list all waiting identifiers,
 * used by the supervisor for presence-based clean-up
 */
interface EnumerableQueue extends QueueInterface
{
    /**
     * Get all identifiers currently in the queue
     *
     * @return list<string> All queue member identifiers, in no guaranteed order
     */
    public function all(): array;
}
