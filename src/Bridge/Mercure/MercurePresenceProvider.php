<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Bridge\Mercure;

use Clegginabox\Airlock\Presence\PresenceProviderInterface;

/**
 * Checks user presence by querying Mercure's subscriptions API.
 *
 * Requires Mercure to have the subscriptions API enabled
 * (MERCURE_EXTRA_DIRECTIVES="subscriptions").
 *
 * Note: This does not use Symfony's HubInterface because it only
 * exposes publish(). Querying subscriptions requires an HTTP GET.
 */
final readonly class MercurePresenceProvider implements PresenceProviderInterface
{
    public function __construct(
        private string $hubUrl,
        private string $jwtToken,
    ) {
    }

    public function isConnected(string $identifier, string $topic): bool
    {
        $subscriptionsUrl = rtrim($this->hubUrl, '/') . '/subscriptions/' . urlencode($topic);

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => sprintf("Authorization: Bearer %s\r\n", $this->jwtToken),
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ]);

        $response = file_get_contents($subscriptionsUrl, false, $context);

        if ($response === false) {
            // If we can't reach Mercure, assume connected (fail-open)
            return true;
        }

        $data = json_decode($response, true);

        if (!is_array($data)) {
            return true;
        }

        // Mercure subscriptions response contains a "subscriptions" array
        $subscriptions = $data['subscriptions'] ?? [];

        return $subscriptions !== [];
    }
}
