<?php

declare(strict_types=1);

namespace App\Listener;

use Clegginabox\Airlock\Event\EntryAdmittedEvent;
use Spiral\Events\Attribute\Listener;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

#[Listener]
class EntryAdmittedListener
{
    public function __construct(
        private readonly HubInterface $hub,
    ) {
    }

    public function __invoke(EntryAdmittedEvent $event): void
    {
        $this->hub->publish(
            new Update(
                topics: $event->airlock,
                data: json_encode([
                    'identifier' => $event->identifier,
                    'event'      => 'entry_admitted',
                ], JSON_THROW_ON_ERROR),
            )
        );
    }
}
