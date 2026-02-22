<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Bridge\Mercure;

use Clegginabox\Airlock\Notifier\AirlockNotifierInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class MercureAirlockNotifier implements AirlockNotifierInterface
{
    public function __construct(private readonly HubInterface $hub)
    {
    }

    public function notify(string $identifier, string $topic, ?string $claimNonce = null): void
    {
        $payload = ['event' => 'your_turn'];

        if ($claimNonce !== null) {
            $payload['claimNonce'] = $claimNonce;
        }

        $this->hub->publish(
            new Update(
                topics: $topic,
                data: json_encode($payload, JSON_THROW_ON_ERROR),
            )
        );
    }
}
