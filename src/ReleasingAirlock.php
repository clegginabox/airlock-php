<?php

declare(strict_types=1);

namespace Clegginabox\Airlock;

use Clegginabox\Airlock\Seal\SealToken;

interface ReleasingAirlock
{
    /**
     * Release an acquired slot.
     *
     * Call this when the user finishes using the resource or their session ends.
     * The slot becomes available for the next user; promotion is handled by
     * the supervisor, not the gate.
     *
     * @param SealToken $token The access token received from enter()
     */
    public function release(SealToken $token): void;
}
