<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Seal;

/**
 * A seal that enforces a cooldown period before re-entry.
 *
 * @todo Implement cooldown logic
 */
class CooldownSeal implements Seal
{
    public function tryAcquire(): ?SealToken
    {
        // TODO: Implement
        return null;
    }
}
