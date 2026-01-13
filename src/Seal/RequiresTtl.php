<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Seal;

/**
 * Marker interface for seals that require a TTL to avoid stalled locks.
 *
 * Implementations must be created with a positive TTL.
 * The lock will eventually expire even if not explicitly released.
 */
interface RequiresTtl
{
}
