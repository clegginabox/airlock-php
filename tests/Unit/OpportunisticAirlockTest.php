<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Tests\Unit;

use Clegginabox\Airlock\OpportunisticAirlock;
use Clegginabox\Airlock\Seal\RefreshableSeal;
use Clegginabox\Airlock\Seal\ReleasableSeal;
use Clegginabox\Airlock\Seal\Seal;
use Clegginabox\Airlock\Seal\SealToken;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OpportunisticAirlockTest extends TestCase
{
    /** @var MockObject<ReleasableSeal&RefreshableSeal> */
    private MockObject $mockSeal;

    private SealToken $mockSealToken;

    private OpportunisticAirlock $airlock;

    public function setUp(): void
    {
        $this->mockSeal = $this->createMockForIntersectionOfInterfaces([
            Seal::class,
            ReleasableSeal::class,
            RefreshableSeal::class,
        ]);

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

        $this->airlock = new OpportunisticAirlock($this->mockSeal);
    }

    public function testEnterWhenAdmitted(): void
    {
        $this->mockSeal->expects($this->once())
            ->method('tryAcquire')
            ->willReturn($this->mockSealToken);

        $entryResult = $this->airlock->enter('test');

        $this->assertTrue($entryResult->isAdmitted());
        $this->assertEquals('', $entryResult->getTopic());
    }

    public function testEnterWhenQueued(): void
    {
        $this->mockSeal->expects($this->once())
            ->method('tryAcquire')
            ->willReturn(null);

        $entryResult = $this->airlock->enter('test');

        $this->assertFalse($entryResult->isAdmitted());
        $this->assertEquals('', $entryResult->getTopic());
        $this->assertEquals(-1, $entryResult->getPosition());
    }

    public function testRelease(): void
    {
        $this->mockSeal->expects($this->once())
            ->method('release')
            ->with($this->mockSealToken);

        $this->airlock->release($this->mockSealToken);
    }

    public function testRefresh(): void
    {
        $this->mockSeal->expects($this->once())
            ->method('refresh')
            ->with($this->mockSealToken);

        $this->airlock->refresh($this->mockSealToken);
    }

    public function testLeave(): void
    {
        // no-op
        $this->airlock->leave('identifier');

        $this->assertTrue(true);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetPositionReturnsNull(): void
    {
        $this->assertNull($this->airlock->getPosition('itsme'));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetTopicReturnsEmptyString(): void
    {
        $this->assertEquals('', $this->airlock->getTopic('itsme'));
    }
}
