<?php

declare(strict_types=1);

namespace Clegginabox\Airlock;

use Clegginabox\Airlock\Seal\SealToken;

interface ReleasingAirlock
{
    /**
     * Release an acquired slot and notify the next queued user.
     *
     * Call this when the user finishes using the resource or their session ends.
     *
     * @param SealToken $token The access token received from enter()
     */
    public function release(SealToken $token): void;
}
