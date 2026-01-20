<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Seal;

/**
 * A seal whose acquired permits can be explicitly released before expiry.
 */
interface ReleasableSeal
{
    /**
     * Release a previously acquired slot.
     */
    public function release(SealToken $token): void;
}
