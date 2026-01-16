<?php

declare(strict_types=1);

namespace Clegginabox\Airlock;

use Clegginabox\Airlock\Seal\SealToken;

/**
 * Main entry point for managing access to a capacity-limited resource.
 *
 * An airlock controls how many users can access a resource simultaneously,
 * queuing additional users until a slot becomes available.
 */
interface AirlockInterface
{
    /**
     * Attempt to enter the airlock.
     *
     * If capacity is available, the user gains immediate access. Otherwise,
     * they are placed in a queue and must wait for a slot to open.
     *
     * @param string $identifier Unique identifier for the user/session
     * @return EntryResult Contains access token if granted, or queue position if waiting
     */
    public function enter(string $identifier, int $priority = 0): EntryResult;

    /**
     * Voluntarily leave the airlock before gaining access.
     *
     * Use this when a user gives up waiting or navigates away.
     *
     * @param string $identifier Unique identifier for the user/session
     */
    public function leave(string $identifier): void;

    /**
     * Release an acquired slot and notify the next queued user.
     *
     * Call this when the user finishes using the resource or their session ends.
     *
     * @param SealToken $token The access token received from enter()
     */
    public function release(SealToken $token): void;

    /**
     * Extend the lease on an acquired slot.
     *
     * @param SealToken $token The current access token
     * @param float|null $ttlInSeconds New TTL, or null to use the default
     * @return SealToken|null New token if refreshed, null if the lease expired
     */
    public function refresh(SealToken $token, ?float $ttlInSeconds = null): ?SealToken;

    /**
     * Get the current queue position for a waiting user.
     *
     * @param string $identifier Unique identifier for the user/session
     * @return int|null Position (1 = next in line), or null if not in queue
     */
    public function getPosition(string $identifier): ?int;

    /**
     * Get the notification topic for real-time updates.
     *
     * @param string $identifier Unique identifier for the user/session
     * @return string Topic URL/identifier for subscribing to updates
     */
    public function getTopic(string $identifier): string;
}
