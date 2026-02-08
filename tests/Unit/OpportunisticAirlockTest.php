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
    /**
     * @var MockObject&Seal&ReleasableSeal&RefreshableSeal
     */
    private MockObject $mockSeal;

    private SealToken $mockSealToken;

    private OpportunisticAirlock $airlock;

    public function setUp(): void
    {
        /** @var MockObject&Seal&ReleasableSeal&RefreshableSeal $mockSeal */
        $mockSeal = $this->createMockForIntersectionOfInterfaces([
            Seal::class,
            ReleasableSeal::class,
            RefreshableSeal::class,
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

        /** @var Seal&ReleasableSeal&RefreshableSeal $seal */
        $seal = $this->mockSeal;
        $this->airlock = new OpportunisticAirlock($seal);
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

    #[AllowMockObjectsWithoutExpectations]
    public function testLeave(): void
    {
        $this->expectNotToPerformAssertions();
        $this->airlock->leave('identifier');
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
