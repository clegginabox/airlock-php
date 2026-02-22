<?php

declare(strict_types=1);

namespace App\Listener;

use Clegginabox\Airlock\Event\LockReleasedEvent;
use Spiral\Events\Attribute\Listener;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

#[Listener]
class LockReleasedListener
{
    public function __construct(
        private readonly HubInterface $hub,
    ) {
    }

    public function __invoke(LockReleasedEvent $event): void
    {
        $this->hub->publish(
            new Update(
                topics: $event->airlock,
                data: json_encode([
                    'event'      => 'lock_released',
                ], JSON_THROW_ON_ERROR),
            )
        );
    }
}
