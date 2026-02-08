<?php

declare(strict_types=1);

namespace Clegginabox\Airlock;

use Clegginabox\Airlock\Seal\SealToken;

interface RefreshingAirlock
{
    /**
     * Extend the lease on an acquired slot.
     *
     * @param SealToken $token The current access token
     * @param float|null $ttlInSeconds New TTL, or null to use the default
     *
     * @return SealToken|null New token if refreshed, null if the lease expired
     */
    public function refresh(SealToken $token, ?float $ttlInSeconds = null): ?SealToken;
}
