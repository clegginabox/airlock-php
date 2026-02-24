<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Presence;

/**
 * No-op presence provider that assumes all users are connected.
 *
 * Use when presence information is unavailable
 * - Polling-based systems
 * - Testing
 * - Deployments without Notifiers
 */
final readonly class NullPresenceProvider implements PresenceProviderInterface
{
    public function isConnected(string $identifier, string $topic): bool
    {
        return true;
    }
}
