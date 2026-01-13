<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Seal;

/**
 * Marker interface for seals that support blocking acquisition.
 *
 * Implementations may wait until the seal becomes available
 * instead of failing immediately.
 */
interface BlockingSeal
{
}
