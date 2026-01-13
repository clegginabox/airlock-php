<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Seal;

use Clegginabox\Airlock\Exception\LeaseExpiredException;

/**
 * Manages capacity slots for the airlock using semaphore-based locking.
 *
 * A seal represents the available capacity of a resource. It wraps
 * Symfony's Lock or Semaphore component to provide distributed slot management.
 */
interface SealInterface
{
    /**
     * Attempt to acquire an available slot.
     *
     * @return string|null Serialized token if a slot was acquired, null if none available
     */
    public function tryAcquire(): ?string;

    /**
     * Release a previously acquired slot.
     *
     * @param string $token The token received from tryAcquire()
     */
    public function release(string $token): void;

    /**
     * Extend the lease on an acquired slot.
     *
     * @param string $token The current token
     * @param float|null $ttlInSeconds New TTL, or null to use the default
     * @return string|null New token if refreshed successfully
     * @throws LeaseExpiredException If the token is no longer valid
     */
    public function refresh(string $token, ?float $ttlInSeconds = null): ?string;

    /**
     * Check if a token's lease has expired.
     *
     * @param string $token The token to check
     * @return bool True if expired, false if still valid
     */
    public function isExpired(string $token): bool;

    /**
     * Check if a token currently holds an acquired slot.
     *
     * @param string $token The token to check
     * @return bool True if the slot is still held
     */
    public function isAcquired(string $token): bool;

    /**
     * Get the remaining lifetime of a token's lease.
     *
     * @param string $token The token to check
     * @return float|null Seconds remaining, or null if unknown/expired
     */
    public function getRemainingLifetime(string $token): ?float;

    /**
     * Get a string representation of the seal for debugging.
     *
     * @return string Human-readable identifier
     */
    public function __toString(): string;
}
