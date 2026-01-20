<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Tests\Unit\Bridge\Mercure;

use Clegginabox\Airlock\Bridge\Mercure\MercureAirlockNotifier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class MercureAirlockNotifierTest extends TestCase
{
    public function testNotifyPublishesUpdateToHub(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('publish')
            ->with($this->callback(function (Update $update): bool {
                return $update->getTopics() === ['my-topic']
                    && $update->getData() === '{"event":"your_turn"}';
            }));

        $notifier = new MercureAirlockNotifier($hub);
        $notifier->notify('my-identifier', 'my-topic');
    }

    public function testNotifyUsesProvidedTopic(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('publish')
            ->with($this->callback(function (Update $update): bool {
                return $update->getTopics() === ['queue/user-123'];
            }));

        $notifier = new MercureAirlockNotifier($hub);
        $notifier->notify('user-123', 'queue/user-123');
    }
}
