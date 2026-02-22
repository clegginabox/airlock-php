<?php

declare(strict_types=1);

namespace App\Listener;

use Clegginabox\Airlock\Event\EntryQueuedEvent;
use Psr\Log\LoggerInterface;
use Spiral\Events\Attribute\Listener;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

#[Listener]
class EntryQueuedListener
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly HubInterface $hub,
    ) {
    }

    public function __invoke(EntryQueuedEvent $event): void
    {
        $this->logger->debug('EntryQueuedListener fired', [
            'identifier' => $event->identifier,
            'airlock' => $event->airlock,
        ]);

        $this->hub->publish(
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
