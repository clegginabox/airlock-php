<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Presence;

/**
 * Determines whether a user is still actively connected.
 *
 * Used by the supervisor to detect disconnected users and clean up
 * abandoned queue entries
 */
interface PresenceProviderInterface
{
    /**
     * Check if a specific user is currently connected
     *
     * @param string $identifier Unique identifier for the user/session
     * @param string $topic The notification topic the user would be subscribed to
     */
    public function isConnected(string $identifier, string $topic): bool;
}
