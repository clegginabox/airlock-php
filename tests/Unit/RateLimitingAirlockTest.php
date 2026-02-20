<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Tests\Unit;

use Clegginabox\Airlock\RateLimitingAirlock;
use Clegginabox\Airlock\Seal\RateLimitingSeal;
use Clegginabox\Airlock\Seal\SealToken;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RateLimitingAirlockTest extends TestCase
{
    private MockObject&RateLimitingSeal $mockSeal;

    private SealToken $mockSealToken;

    private RateLimitingAirlock $airlock;

    protected function setUp(): void
    {
        $this->mockSeal = $this->createMock(RateLimitingSeal::class);

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
                return 'resource';
            }
        };

        $this->airlock = new RateLimitingAirlock($this->mockSeal);
    }

    public function testEnterWhenAdmitted(): void
    {
        $this->mockSeal->expects($this->once())
            ->method('tryAcquire')
            ->willReturn($this->mockSealToken);

        $entryResult = $this->airlock->enter('client-1');

        $this->assertTrue($entryResult->isAdmitted());
        $this->assertSame($this->mockSealToken, $entryResult->getToken());
        $this->assertSame('', $entryResult->getTopic());
    }

    public function testEnterWhenQueued(): void
    {
        $this->mockSeal->expects($this->once())
            ->method('tryAcquire')
            ->willReturn(null);

        $entryResult = $this->airlock->enter('client-1');

        $this->assertFalse($entryResult->isAdmitted());
        $this->assertNull($entryResult->getToken());
        $this->assertSame(-1, $entryResult->getPosition());
        $this->assertSame('', $entryResult->getTopic());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testLeaveIsNoOp(): void
    {
        $this->expectNotToPerformAssertions();
        $this->airlock->leave('client-1');
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetPositionReturnsNull(): void
    {
        $this->assertNull($this->airlock->getPosition('client-1'));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetTopicReturnsEmptyString(): void
    {
        $this->assertSame('', $this->airlock->getTopic('client-1'));
    }
}
