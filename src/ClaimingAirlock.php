<?php

declare(strict_types=1);

namespace Clegginabox\Airlock;

interface ClaimingAirlock
{
    public function claim(string $identifier, string $reservationNonce): ClaimResult;

    public function getReservationNonce(string $identifier): ?string;
}
