<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Seal;

use Clegginabox\Airlock\Exception\LeaseExpiredException;

/**
 * The acquired permit has a lease that can be extended.
 */
interface RefreshableSeal
{
    /** @throws LeaseExpiredException */
    public function refresh(string $token, ?float $ttlInSeconds = null): string;
}
