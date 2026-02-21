<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Tests\Unit\Decorator;

use Clegginabox\Airlock\Airlock;
use Clegginabox\Airlock\Decorator\LoggingAirlock;
use Clegginabox\Airlock\EntryResult;
use Clegginabox\Airlock\RefreshingAirlock;
use Clegginabox\Airlock\ReleasingAirlock;
use Clegginabox\Airlock\Seal\SealToken;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class LoggingAirlockTest extends TestCase
{
    private MockObject&LoggerInterface $mockLogger;

    private SealToken $mockSealToken;

    protected function setUp(): void
    {
        $this->mockLogger = $this->createMock(LoggerInterface::class);

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

    public function testEnterLogsAdmitted(): void
    {
        $innerResult = EntryResult::admitted($this->mockSealToken, '/waiting-room/user-1');

        $inner = $this->createMock(Airlock::class);
        $inner->expects($this->once())
            ->method('enter')
            ->with('user-1', 0)
            ->willReturn($innerResult);

        $this->mockLogger->expects($this->once())
            ->method('debug')
            ->with('Attempting to enter airlock', [
                'identifier' => 'user-1',
                'priority' => 0,
            ]);

        $this->mockLogger->expects($this->once())
            ->method('info')
            ->with('Admitted to airlock', [
                'identifier' => 'user-1',
                'token' => 'token-string',
            ]);

        $airlock = new LoggingAirlock($inner, $this->mockLogger);
        $result = $airlock->enter('user-1');

        $this->assertTrue($result->isAdmitted());
        $this->assertSame($this->mockSealToken, $result->getToken());
    }

    public function testEnterLogsQueued(): void
    {
        $innerResult = EntryResult::queued(3, '/waiting-room/user-1');

        $inner = $this->createMock(Airlock::class);
        $inner->expects($this->once())
            ->method('enter')
            ->with('user-1', 5)
            ->willReturn($innerResult);

        $this->mockLogger->expects($this->once())
            ->method('info')
            ->with('Queued in airlock', [
                'identifier' => 'user-1',
                'position' => 3,
            ]);

        $airlock = new LoggingAirlock($inner, $this->mockLogger);
        $result = $airlock->enter('user-1', 5);

        $this->assertFalse($result->isAdmitted());
        $this->assertSame(3, $result->getPosition());
    }

    public function testLeaveLogsIdentifier(): void
    {
        $inner = $this->createMock(Airlock::class);
        $inner->expects($this->once())
            ->method('leave')
            ->with('user-1');

        $this->mockLogger->expects($this->once())
            ->method('info')
            ->with('Left airlock queue', [
                'identifier' => 'user-1',
            ]);

        $airlock = new LoggingAirlock($inner, $this->mockLogger);
        $airlock->leave('user-1');
    }

    public function testReleaseLogsToken(): void
    {
        /** @var MockObject&Airlock&ReleasingAirlock $inner */
        $inner = $this->createMockForIntersectionOfInterfaces([
            Airlock::class,
            ReleasingAirlock::class,
        ]);

        $inner->expects($this->once())
            ->method('release')
            ->with($this->mockSealToken);

        $this->mockLogger->expects($this->once())
            ->method('info')
            ->with('Released airlock lock', [
                'token' => 'token-string',
            ]);

        $airlock = new LoggingAirlock($inner, $this->mockLogger);
        $airlock->release($this->mockSealToken);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testReleaseThrowsWhenInnerDoesNotSupportIt(): void
    {
        $inner = $this->createMock(Airlock::class);

        $airlock = new LoggingAirlock($inner, $this->mockLogger);

        $this->expectException(\LogicException::class);
        $airlock->release($this->mockSealToken);
    }

    public function testRefreshLogsTokens(): void
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

        $this->mockLogger->expects($this->once())
            ->method('info')
            ->with('Refreshed airlock lease', [
                'old_token' => 'token-string',
                'new_token' => 'new-token-string',
                'ttl' => 30.0,
            ]);

        $airlock = new LoggingAirlock($inner, $this->mockLogger);
        $result = $airlock->refresh($this->mockSealToken, 30.0);

        $this->assertSame($newToken, $result);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testRefreshThrowsWhenInnerDoesNotSupportIt(): void
    {
        $inner = $this->createMock(Airlock::class);

        $airlock = new LoggingAirlock($inner, $this->mockLogger);

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

        $airlock = new LoggingAirlock($inner, $this->mockLogger);

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

        $airlock = new LoggingAirlock($inner, $this->mockLogger);

        $this->assertSame('/waiting-room/user-1', $airlock->getTopic('user-1'));
    }
}
