<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Seal;

/**
 * Base interface for all seal implementations.
 *
 * A seal controls access to a limited resource (mutex, semaphore, rate limit).
 */
interface Seal
{
    /**
     * Attempt to acquire a slot.
     *
     * @return SealToken|null Token if acquired, null if unavailable
     */
    public function tryAcquire(): ?SealToken;
}
