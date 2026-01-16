<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Seal;

/**
 * A rate-limiting seal that grants up to N admits per window.
 *
 * @todo Implement rate limiting logic
 */
class WindowRateSeal implements Seal
{
    public function tryAcquire(): ?SealToken
    {
        // TODO: Implement
        return null;
    }
}
