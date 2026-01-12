<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Seal;

use Clegginabox\Airlock\Exception\LeaseExpiredException;

/**
 * Wrapper around Symfony\Lock or Symfony\Semaphore
 */
interface SealInterface
{
    public function tryAcquire(): ?string; // returns serialized token or null

    public function release(string $token): void;

    /**
     * @throws LeaseExpiredException If the token is no longer valid
     */
    public function refresh(string $token, ?float $ttlInSeconds = null): ?string;

    public function isExpired(string $token): bool;

    public function isAcquired(string $token): bool;

    public function getRemainingLifetime(string $token): ?float;

    public function __toString(): string;
}
