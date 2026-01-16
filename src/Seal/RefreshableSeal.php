<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Seal;

use Clegginabox\Airlock\Exception\LeaseExpiredException;

/**
 * A seal whose acquired permits have a lease that can be extended.
 */
interface RefreshableSeal extends Seal
{
    /**
     * Extend the lease on an acquired slot.
     *
     * @throws LeaseExpiredException If the token is no longer valid
     */
    public function refresh(SealToken $token, ?float $ttlInSeconds = null): SealToken;
}
