<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Tests\Unit\Bridge\Symfony\Seal;

use Clegginabox\Airlock\Bridge\Symfony\Seal\SymfonyRateLimiterSeal;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;

class SymfonyRateLimiterSealTest extends TestCase
{
    public function testReturnsTokenWhenAcquireSucceeds(): void
    {
        $mockLimiter = $this->createMock(LimiterInterface::class);

        $rateLimit = new RateLimit(
            100,
            new DateTimeImmutable(),
            true,
            100
        );

        $mockLimiter->expects($this->once())
            ->method('consume')
            ->with(1)
            ->willReturn($rateLimit);

        $seal = new SymfonyRateLimiterSeal($mockLimiter, 'test-resource');
        $token = $seal->tryAcquire();

        $this->assertNotNull($token);
        $this->assertSame('test-resource', $token->getResource());
    }

    public function testReturnsNullWhenAcquireFails(): void
    {
        $mockLimiter = $this->createMock(LimiterInterface::class);

        $rateLimit = new RateLimit(
            0,
            new DateTimeImmutable(),
            false,
            100
        );

        $mockLimiter->expects($this->once())
            ->method('consume')
            ->with(1)
            ->willReturn($rateLimit);

        $seal = new SymfonyRateLimiterSeal($mockLimiter, 'test-resource');
        $token = $seal->tryAcquire();

        $this->assertNull($token);
    }
}
