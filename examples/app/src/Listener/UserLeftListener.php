<?php

declare(strict_types=1);

namespace App\Listener;

use Clegginabox\Airlock\Event\UserLeftEvent;
use Spiral\Events\Attribute\Listener;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

#[Listener]
class UserLeftListener
{
    public function __construct(
        private readonly HubInterface $hub,
    ) {
    }

    public function __invoke(UserLeftEvent $event): void
    {
        $this->hub->publish(
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
