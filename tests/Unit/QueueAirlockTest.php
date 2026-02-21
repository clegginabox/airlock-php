<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Tests\Unit;

use Clegginabox\Airlock\Notifier\AirlockNotifierInterface;
use Clegginabox\Airlock\Queue\LotteryQueue;
use Clegginabox\Airlock\QueueAirlock;
use Clegginabox\Airlock\Seal\RefreshableSeal;
use Clegginabox\Airlock\Seal\ReleasableSeal;
use Clegginabox\Airlock\Seal\Seal;
use Clegginabox\Airlock\Seal\SealToken;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class QueueAirlockTest extends TestCase
{
    /**
     * @var MockObject&Seal&ReleasableSeal&RefreshableSeal
     */
    private MockObject $mockSeal;

    private SealToken $mockSealToken;

    private MockObject&LotteryQueue $mockQueue;

    private MockObject&AirlockNotifierInterface $mockNotifier;

    private QueueAirlock $airlock;

    protected function setUp(): void
    {
        /** @var MockObject&Seal&ReleasableSeal&RefreshableSeal $mockSeal */
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

        $this->mockNotifier = $this->createMock(AirlockNotifierInterface::class);
        $this->mockQueue = $this->createMock(LotteryQueue::class);

        /** @var Seal&ReleasableSeal $seal */
        $seal = $this->mockSeal;

        $this->airlock = new QueueAirlock($seal, $this->mockQueue, $this->mockNotifier);
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
    public function testReleaseWithNullNextPassenger(): void
    {
        $this->mockSeal->expects($this->once())
            ->method('release')
            ->with($this->mockSealToken);

        $this->mockQueue->expects($this->once())
            ->method('peek')
            ->willReturn(null);

        $this->airlock->release($this->mockSealToken);
    }

    public function testReleaseWithNextPassenger(): void
    {
        $this->mockSeal->expects($this->once())
            ->method('release')
            ->with($this->mockSealToken);

        $this->mockQueue->expects($this->once())
            ->method('peek')
            ->willReturn('identifier');

        $this->mockNotifier->expects($this->once())
            ->method('notify')
            ->with('identifier', '/waiting-room/identifier');

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
}
