<?php

declare(strict_types=1);

namespace App\Listener;

use Clegginabox\Airlock\Bridge\Symfony\Mercure\SymfonyMercureHubFactory;
use Clegginabox\Airlock\Event\EntryQueuedEvent;
use Spiral\Events\Attribute\Listener;
use Symfony\Component\Mercure\Update;

#[Listener]
class EntryQueuedListener
{
    public function __invoke(EntryQueuedEvent $event): void
    {
        $hubUrl = getenv('MERCURE_HUB_URL') ?: 'http://localhost/.well-known/mercure';
        $jwtSecret = getenv('MERCURE_JWT_SECRET') ?: 'airlock-mercure-secret-32chars-minimum';
        $hub = SymfonyMercureHubFactory::create($hubUrl, $jwtSecret);

        $hub->publish(
            new Update(
                topics: $event->airlock,
                data: json_encode([
                    'identifier' => $event->identifier,
                    'event'      => 'entry_queued',
                ], JSON_THROW_ON_ERROR),
            )
        );
    }
}
