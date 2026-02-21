<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Tests\Unit\Decorator;

use Clegginabox\Airlock\Airlock;
use Clegginabox\Airlock\Decorator\EventDispatchingAirlock;
use Clegginabox\Airlock\EntryResult;
use Clegginabox\Airlock\Event\EntryAdmittedEvent;
use Clegginabox\Airlock\Event\EntryQueuedEvent;
use Clegginabox\Airlock\Event\LeaseRefreshedEvent;
use Clegginabox\Airlock\Event\LockReleasedEvent;
use Clegginabox\Airlock\Event\UserLeftEvent;
use Clegginabox\Airlock\RefreshingAirlock;
use Clegginabox\Airlock\ReleasingAirlock;
use Clegginabox\Airlock\Seal\SealToken;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

class EventDispatchingAirlockTest extends TestCase
{
    private MockObject&EventDispatcherInterface $mockDispatcher;

    private SealToken $mockSealToken;

    protected function setUp(): void
    {
        $this->mockDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->mockSealToken = new class implements SealToken {
            public function getResource(): string
            {
                return 'resource';
            }

            public function getId(): string
            {
                return 'id';
            }

            public function __toString(): string
            {
                return 'token-string';
            }
        };
    }

    public function testEnterDispatchesAdmittedEvent(): void
    {
        $innerResult = EntryResult::admitted($this->mockSealToken, '/waiting-room/user-1');

        $inner = $this->createMock(Airlock::class);
        $inner->expects($this->once())
            ->method('enter')
            ->with('user-1', 0)
            ->willReturn($innerResult);

        $this->mockDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (mixed $event): bool {
                $this->assertInstanceOf(EntryAdmittedEvent::class, $event);
                $this->assertSame('user-1', $event->identifier);
                $this->assertSame($this->mockSealToken, $event->token);
                $this->assertSame('/waiting-room/user-1', $event->topic);

                return true;
            }));

        $airlock = new EventDispatchingAirlock($inner, $this->mockDispatcher);
        $result = $airlock->enter('user-1');

        $this->assertTrue($result->isAdmitted());
    }

    public function testEnterDispatchesQueuedEvent(): void
    {
        $innerResult = EntryResult::queued(5, '/waiting-room/user-1');

        $inner = $this->createMock(Airlock::class);
        $inner->expects($this->once())
            ->method('enter')
            ->with('user-1', 0)
            ->willReturn($innerResult);

        $this->mockDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (mixed $event): bool {
                $this->assertInstanceOf(EntryQueuedEvent::class, $event);
                $this->assertSame('user-1', $event->identifier);
                $this->assertSame(5, $event->position);
                $this->assertSame('/waiting-room/user-1', $event->topic);

                return true;
            }));

        $airlock = new EventDispatchingAirlock($inner, $this->mockDispatcher);
        $result = $airlock->enter('user-1');

        $this->assertFalse($result->isAdmitted());
    }

    public function testLeaveDispatchesUserLeftEvent(): void
    {
        $inner = $this->createMock(Airlock::class);
        $inner->expects($this->once())
            ->method('leave')
            ->with('user-1');

        $this->mockDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (mixed $event): bool {
                $this->assertInstanceOf(UserLeftEvent::class, $event);
                $this->assertSame('user-1', $event->identifier);

                return true;
            }));

        $airlock = new EventDispatchingAirlock($inner, $this->mockDispatcher);
        $airlock->leave('user-1');
    }

    public function testReleaseDispatchesLockReleasedEvent(): void
    {
        /** @var MockObject&Airlock&ReleasingAirlock $inner */
        $inner = $this->createMockForIntersectionOfInterfaces([
            Airlock::class,
            ReleasingAirlock::class,
        ]);

        $inner->expects($this->once())
            ->method('release')
            ->with($this->mockSealToken);

        $this->mockDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (mixed $event): bool {
                $this->assertInstanceOf(LockReleasedEvent::class, $event);
                $this->assertSame($this->mockSealToken, $event->token);

                return true;
            }));

        $airlock = new EventDispatchingAirlock($inner, $this->mockDispatcher);
        $airlock->release($this->mockSealToken);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testReleaseThrowsWhenInnerDoesNotSupportIt(): void
    {
        $inner = $this->createMock(Airlock::class);

        $airlock = new EventDispatchingAirlock($inner, $this->mockDispatcher);

        $this->expectException(\LogicException::class);
        $airlock->release($this->mockSealToken);
    }

    public function testRefreshDispatchesLeaseRefreshedEvent(): void
    {
        $newToken = new class implements SealToken {
            public function getResource(): string
            {
                return 'resource';
            }

            public function getId(): string
            {
                return 'new-id';
            }

            public function __toString(): string
            {
                return 'new-token-string';
            }
        };

        /** @var MockObject&Airlock&RefreshingAirlock $inner */
        $inner = $this->createMockForIntersectionOfInterfaces([
            Airlock::class,
            RefreshingAirlock::class,
        ]);

        $inner->expects($this->once())
            ->method('refresh')
            ->with($this->mockSealToken, 30.0)
            ->willReturn($newToken);

        $this->mockDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (mixed $event) use ($newToken): bool {
                $this->assertInstanceOf(LeaseRefreshedEvent::class, $event);
                $this->assertSame($this->mockSealToken, $event->oldToken);
                $this->assertSame($newToken, $event->newToken);
                $this->assertSame(30.0, $event->ttlInSeconds);

                return true;
            }));

        $airlock = new EventDispatchingAirlock($inner, $this->mockDispatcher);
        $result = $airlock->refresh($this->mockSealToken, 30.0);

        $this->assertSame($newToken, $result);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testRefreshThrowsWhenInnerDoesNotSupportIt(): void
    {
        $inner = $this->createMock(Airlock::class);

        $airlock = new EventDispatchingAirlock($inner, $this->mockDispatcher);

        $this->expectException(\LogicException::class);
        $airlock->refresh($this->mockSealToken);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetPositionDelegates(): void
    {
        $inner = $this->createMock(Airlock::class);
        $inner->expects($this->once())
            ->method('getPosition')
            ->with('user-1')
            ->willReturn(4);

        $airlock = new EventDispatchingAirlock($inner, $this->mockDispatcher);

        $this->assertSame(4, $airlock->getPosition('user-1'));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetTopicDelegates(): void
    {
        $inner = $this->createMock(Airlock::class);
        $inner->expects($this->once())
            ->method('getTopic')
            ->with('user-1')
            ->willReturn('/waiting-room/user-1');

        $airlock = new EventDispatchingAirlock($inner, $this->mockDispatcher);

        $this->assertSame('/waiting-room/user-1', $airlock->getTopic('user-1'));
    }
}
