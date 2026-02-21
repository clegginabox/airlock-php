<?php

declare(strict_types=1);

namespace App\Listener;

use Clegginabox\Airlock\Bridge\Symfony\Mercure\SymfonyMercureHubFactory;
use Clegginabox\Airlock\Event\UserLeftEvent;
use Spiral\Events\Attribute\Listener;
use Symfony\Component\Mercure\Update;

#[Listener]
class UserLeftListener
{
    public function __invoke(UserLeftEvent $event): void
    {
        $hubUrl = getenv('MERCURE_HUB_URL') ?: 'http://localhost/.well-known/mercure';
        $jwtSecret = getenv('MERCURE_JWT_SECRET') ?: 'airlock-mercure-secret-32chars-minimum';
        $hub = SymfonyMercureHubFactory::create($hubUrl, $jwtSecret);

        $hub->publish(
            new Update(
                topics: $event->airlock,
                data: json_encode([
                    'identifier' => $event->identifier,
                    'event'      => 'user_left',
                ], JSON_THROW_ON_ERROR),
            )
        );
    }
}
