<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Reservation;

interface ReservationStoreInterface
{
    public function reserve(string $identifier, int $ttlSeconds): string;

    public function isReservedFor(string $identifier, string $nonce): bool;

    public function consume(string $identifier, string $nonce): bool;

    public function getReservationNonce(string $identifier): ?string;

    public function clear(string $identifier): void;
}
