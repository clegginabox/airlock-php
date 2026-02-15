<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Tests\Unit\Seal;

use Clegginabox\Airlock\Seal\CompositeSeal;
use Clegginabox\Airlock\Seal\ReleasableSeal;
use Clegginabox\Airlock\Seal\Seal;
use Clegginabox\Airlock\Seal\SealToken;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CompositeSealTest extends TestCase
{
    public function testCanAcquireWhenLockAndRateLimiterHaveCapacity(): void
    {
        /** @var MockObject&Seal&ReleasableSeal $lockSeal */
        $lockSeal = $this->createMockForIntersectionOfInterfaces([
            Seal::class,
            ReleasableSeal::class,
        ]);

        $rateLimiterSeal = $this->createMock(Seal::class);

        $lockToken = $this->createSealToken('lock-resource', 'lock-id');
        $limiterToken = $this->createSealToken('limiter-resource', 'limiter-id');

        $lockSeal->expects($this->once())
            ->method('tryAcquire')
            ->willReturn($lockToken);

        $lockSeal->expects($this->never())
            ->method('release');

        $rateLimiterSeal->expects($this->once())
            ->method('tryAcquire')
            ->willReturn($limiterToken);

        $compositeSeal = new CompositeSeal($lockSeal, $rateLimiterSeal);

        $this->assertSame($lockToken, $compositeSeal->tryAcquire());
    }

    public function testCannotAcquireWhenLockDoesNotHaveCapacity(): void
    {
        /** @var MockObject&Seal&ReleasableSeal $lockSeal */
        $lockSeal = $this->createMockForIntersectionOfInterfaces([
            Seal::class,
            ReleasableSeal::class,
        ]);

        $lockSeal->expects($this->once())
            ->method('tryAcquire')
            ->willReturn(null);

        $lockSeal->expects($this->never())
            ->method('release');

        $rateLimiterSeal = $this->createMock(Seal::class);
        $rateLimiterSeal->expects($this->never())
            ->method('tryAcquire');

        $compositeSeal = new CompositeSeal($lockSeal, $rateLimiterSeal);
        $this->assertNull($compositeSeal->tryAcquire());
    }

    public function testCannotAcquireWhenRateLimiterDoesNotHaveCapacity(): void
    {
        /** @var MockObject&Seal&ReleasableSeal $lockSeal */
        $lockSeal = $this->createMockForIntersectionOfInterfaces([
            Seal::class,
            ReleasableSeal::class,
        ]);

        $lockToken = $this->createSealToken('lock-resource', 'lock-id');

        $lockSeal->expects($this->once())
            ->method('tryAcquire')
            ->willReturn($lockToken);

        // Assert slot is released
        $lockSeal->expects($this->once())
            ->method('release')
            ->with($this->identicalTo($lockToken));

        $rateLimiterSeal = $this->createMock(Seal::class);

        $rateLimiterSeal->expects($this->once())
            ->method('tryAcquire')
            ->willReturn(null);

        $compositeSeal = new CompositeSeal($lockSeal, $rateLimiterSeal);
        $this->assertNull($compositeSeal->tryAcquire());
    }

    public function testReleaseDelegatesToLockingSeal(): void
    {
        /** @var MockObject&Seal&ReleasableSeal $lockSeal */
        $lockSeal = $this->createMockForIntersectionOfInterfaces([
            Seal::class,
            ReleasableSeal::class,
        ]);

        $token = $this->createSealToken('resource', 'token-id');

        $lockSeal->expects($this->once())
            ->method('release')
            ->with($this->identicalTo($token));

        $compositeSeal = new CompositeSeal($lockSeal, $this->createStub(Seal::class));
        $compositeSeal->release($token);
    }

    private function createSealToken(string $resource, string $id): SealToken
    {
        return new class ($resource, $id) implements SealToken {
            public function __construct(
                private readonly string $resource,
                private readonly string $id,
            ) {
            }

            public function getResource(): string
            {
                return $this->resource;
            }

            public function getId(): string
            {
                return $this->id;
            }

            public function __toString(): string
            {
                return $this->resource . ':' . $this->id;
            }
        };
    }
}
