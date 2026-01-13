<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Seal;

/**
 * Marker interface for seals that do NOT support early release.
 *
 * Typically used for cooldowns and rate-limiting.
 */
interface NonReleasableSeal
{
}
