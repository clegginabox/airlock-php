<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Tests\Unit;

use Clegginabox\Airlock\Notifier\AirlockNotifierInterface;
use Clegginabox\Airlock\Queue\LotteryQueue;
use Clegginabox\Airlock\Queue\QueueInterface;
use Clegginabox\Airlock\QueueAirlock;
use Clegginabox\Airlock\Reservation\ReservationStoreInterface;
use Clegginabox\Airlock\Seal\ReleasableSeal;
use Clegginabox\Airlock\Seal\Seal;
use Clegginabox\Airlock\Seal\SealToken;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class QueueAirlockTest extends TestCase
{
    /**
     * @var MockObject&Seal&ReleasableSeal
     */
    private MockObject $mockSeal;

    private SealToken $mockSealToken;

    private MockObject&LotteryQueue $mockQueue;

    private QueueAirlock $airlock;

    protected function setUp(): void
    {
        /** @var MockObject&Seal&ReleasableSeal $mockSeal */
        $mockSeal = $this->createMockForIntersectionOfInterfaces([
            Seal::class,
            ReleasableSeal::class,
        ]);

        $this->mockSeal = $mockSeal;

        $this->mockSealToken = new class implements SealToken {
            public function getResource(): string
            {
                return 'resource';
            }

            public function getId(): string
            {
                return 'id';
            }

            public function __toString()
            {
                return 'resource';
            }
        };

        $this->mockQueue = $this->createMock(LotteryQueue::class);

        /** @var Seal&ReleasableSeal $seal */
        $seal = $this->mockSeal;

        $this->airlock = new QueueAirlock($seal, $this->mockQueue);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testEnterNotAtFrontOfLine(): void
    {
        $this->mockQueue->expects($this->once())
            ->method('add')
            ->with('identifier')
            ->willReturn(6);

        $this->mockQueue->expects($this->once())
            ->method('peek');

        $entryResult = $this->airlock->enter('identifier');

        $this->assertFalse($entryResult->isAdmitted());
        $this->assertNull($entryResult->getToken());
        $this->assertSame(6, $entryResult->getPosition());
        $this->assertSame('/waiting-room/identifier', $entryResult->getTopic());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFrontOfLineAndEntering(): void
    {
        $this->mockQueue->expects($this->once())
            ->method('add')
            ->with('identifier')
            ->willReturn(1);

        $this->mockSeal->expects($this->once())
            ->method('tryAcquire')
            ->willReturn($this->mockSealToken);

        $this->mockQueue->expects($this->once())
            ->method('remove')
            ->with('identifier');

        $entryResult = $this->airlock->enter('identifier');

        $this->assertTrue($entryResult->isAdmitted());
        $this->assertSame($this->mockSealToken, $entryResult->getToken());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFrontOfLineAndWaiting(): void
    {
        $this->mockQueue->expects($this->once())
            ->method('add')
            ->with('identifier')
            ->willReturn(1);

        $this->mockSeal->expects($this->once())
            ->method('tryAcquire')
            ->willReturn(null);

        $entryResult = $this->airlock->enter('identifier');

        $this->assertFalse($entryResult->isAdmitted());
        $this->assertNull($entryResult->getToken());
        $this->assertSame(1, $entryResult->getPosition());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testLeave(): void
    {
        $this->mockQueue->expects($this->once())
            ->method('remove')
            ->with('identifier');

        $this->airlock->leave('identifier');
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testRelease(): void
    {
        $this->mockSeal->expects($this->once())
            ->method('release')
            ->with($this->mockSealToken);

        $this->airlock->release($this->mockSealToken);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetPosition(): void
    {
        $this->mockQueue->expects($this->once())
            ->method('getPosition')
            ->with('identifier')
            ->willReturn(1);

        $this->assertSame(1, $this->airlock->getPosition('identifier'));
    }

    public function testCreateSupervisorUsesAirlockTopicPrefix(): void
    {
        $this->mockQueue->expects($this->once())
            ->method('peek')
            ->willReturn('identifier');

        $this->mockSeal->expects($this->once())
            ->method('tryAcquire')
            ->willReturn($this->mockSealToken);

        $this->mockSeal->expects($this->once())
            ->method('release')
            ->with($this->mockSealToken);

        $notifier = $this->createMock(AirlockNotifierInterface::class);
        $notifier->expects($this->once())
            ->method('notify')
            ->with('identifier', '/custom-topic/identifier', null);

        $seal = $this->mockSeal;

        $airlock = new QueueAirlock($seal, $this->mockQueue, '/custom-topic');
        $supervisor = $airlock->createSupervisor($notifier);

        $result = $supervisor->tick();

        $this->assertSame('identifier', $result->notified);
    }

    public function testCreateSupervisorSkipsNotificationWhenNoSlotIsAvailable(): void
    {
        $this->mockQueue->expects($this->once())
            ->method('peek')
            ->willReturn('identifier');

        $this->mockSeal->expects($this->once())
            ->method('tryAcquire')
            ->willReturn(null);

        $this->mockSeal->expects($this->never())
            ->method('release');

        $notifier = $this->createMock(AirlockNotifierInterface::class);
        $notifier->expects($this->never())
            ->method('notify');

        $seal = $this->mockSeal;

        $airlock = new QueueAirlock($seal, $this->mockQueue, '/custom-topic');
        $supervisor = $airlock->createSupervisor($notifier);

        $result = $supervisor->tick();

        $this->assertNull($result->notified);
        $this->assertSame([], $result->evicted);
    }

    public function testCreateSupervisorRequiresEnumerableQueue(): void
    {
        $nonEnumerableQueue = $this->createMock(QueueInterface::class);
        $notifier = $this->createMock(AirlockNotifierInterface::class);

        $seal = $this->mockSeal;

        $airlock = new QueueAirlock($seal, $nonEnumerableQueue);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('does not implement');

        $airlock->createSupervisor($notifier);
    }

    public function testClaimAdmitsWhenReservationMatches(): void
    {
        $reservations = $this->createMock(ReservationStoreInterface::class);

        $reservations->expects($this->once())
            ->method('isReservedFor')
            ->with('identifier', 'nonce-1')
            ->willReturn(true);

        $this->mockSeal->expects($this->once())
            ->method('tryAcquire')
            ->willReturn($this->mockSealToken);

        $reservations->expects($this->once())
            ->method('consume')
            ->with('identifier', 'nonce-1')
            ->willReturn(true);

        $this->mockQueue->expects($this->once())
            ->method('remove')
            ->with('identifier');

        $seal = $this->mockSeal;
        $airlock = new QueueAirlock($seal, $this->mockQueue, '/custom-topic', $reservations);

        $result = $airlock->claim('identifier', 'nonce-1');

        $this->assertTrue($result->isAdmitted());
        $this->assertSame($this->mockSealToken, $result->getToken());
    }

    public function testClaimReturnsMissedWhenReservationDoesNotMatch(): void
    {
        $reservations = $this->createMock(ReservationStoreInterface::class);
        $reservations->expects($this->once())
            ->method('isReservedFor')
            ->with('identifier', 'bad-nonce')
            ->willReturn(false);

        $this->mockSeal->expects($this->never())
            ->method('tryAcquire');

        $seal = $this->mockSeal;
        $airlock = new QueueAirlock($seal, $this->mockQueue, '/custom-topic', $reservations);

        $result = $airlock->claim('identifier', 'bad-nonce');

        $this->assertTrue($result->isMissed());
    }

    public function testClaimReturnsUnavailableWhenSlotCannotBeAcquired(): void
    {
        $reservations = $this->createMock(ReservationStoreInterface::class);
        $reservations->expects($this->once())
            ->method('isReservedFor')
            ->with('identifier', 'nonce-1')
            ->willReturn(true);

        $this->mockSeal->expects($this->once())
            ->method('tryAcquire')
            ->willReturn(null);

        $reservations->expects($this->never())
            ->method('consume');

        $seal = $this->mockSeal;
        $airlock = new QueueAirlock($seal, $this->mockQueue, '/custom-topic', $reservations);

        $result = $airlock->claim('identifier', 'nonce-1');

        $this->assertTrue($result->isUnavailable());
    }

    public function testEnterClearsReservationWhenUserIsAdmitted(): void
    {
        $reservations = $this->createMock(ReservationStoreInterface::class);

        $this->mockQueue->expects($this->once())
            ->method('add')
            ->with('identifier')
            ->willReturn(1);

        $this->mockSeal->expects($this->once())
            ->method('tryAcquire')
            ->willReturn($this->mockSealToken);

        $this->mockQueue->expects($this->once())
            ->method('remove')
            ->with('identifier');

        $reservations->expects($this->once())
            ->method('clear')
            ->with('identifier');

        $seal = $this->mockSeal;
        $airlock = new QueueAirlock($seal, $this->mockQueue, '/custom-topic', $reservations);

        $result = $airlock->enter('identifier');

        $this->assertTrue($result->isAdmitted());
    }
}
