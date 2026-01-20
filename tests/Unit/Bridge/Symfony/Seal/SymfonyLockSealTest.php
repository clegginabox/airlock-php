<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Tests\Unit\Bridge\Symfony\Seal;

use Clegginabox\Airlock\Bridge\Symfony\Seal\SymfonyLockSeal;
use Clegginabox\Airlock\Bridge\Symfony\Seal\SymfonyLockToken;
use Clegginabox\Airlock\Exception\LeaseExpiredException;
use Clegginabox\Airlock\Seal\SealToken;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Exception\LockExpiredException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;

class SymfonyLockSealTest extends TestCase
{
    private SharedLockInterface $mockLock;

    private LockFactory $mockFactory;

    private SymfonyLockSeal $seal;

    protected function setUp(): void
    {
        $this->mockLock = $this->createMock(SharedLockInterface::class);
        $this->mockFactory = $this->createMock(LockFactory::class);

        $this->seal = new SymfonyLockSeal(
            $this->mockFactory,
            'lock-seal-test',
            300,
            false
        );
    }

    public function testItAcquiresLock(): void
    {
        $this->mockFactory->expects($this->exactly(2))
            ->method('createLockFromKey')
            ->with(
                $this->callback(fn ($key) => $key instanceof Key && (string) $key === 'lock-seal-test'),
                300.0,
                false
            )
            ->willReturn($this->mockLock);

        $this->mockLock->expects($this->once())
            ->method('acquire')
            ->willReturn(true);

        $this->mockLock->expects($this->once())
            ->method('isAcquired')
            ->willReturn(true);

        $token = $this->seal->tryAcquire();

        $this->assertInstanceof(SymfonyLockToken::class, $token);
        $this->assertTrue($this->seal->isAcquired($token));
    }

    public function testItFailsToAcquireLock(): void
    {
        $this->mockFactory->expects($this->once())
            ->method('createLockFromKey')
            ->willReturn($this->mockLock);

        $this->mockLock->expects($this->once())
            ->method('acquire')
            ->willReturn(false);

        $token = $this->seal->tryAcquire();

        $this->assertNull($token);
    }

    public function testItReleasesLock(): void
    {
        $this->mockFactory->expects($this->exactly(2))
            ->method('createLockFromKey')
            ->willReturn($this->mockLock);

        $this->mockLock->expects($this->once())
            ->method('acquire')
            ->willReturn(true);

        $this->mockLock->expects($this->once())
            ->method('release');

        $token = $this->seal->tryAcquire();
        $this->assertInstanceof(SymfonyLockToken::class, $token);

        $this->seal->release($token);
    }

    public function testReleaseWithInvalidTokenTypeDoesNothing(): void
    {
        $invalidToken = $this->createMock(SealToken::class);

        $this->mockFactory->expects($this->never())
            ->method('createLockFromKey');

        $this->seal->release($invalidToken);
    }

    public function testRefreshWithDefaultTtl(): void
    {
        $key = new Key('lock-seal-test');
        $token = new SymfonyLockToken($key);

        $this->mockFactory->expects($this->once())
            ->method('createLockFromKey')
            ->with($key, 300.0)
            ->willReturn($this->mockLock);

        $this->mockLock->expects($this->once())
            ->method('refresh')
            ->with(300.0);

        $result = $this->seal->refresh($token);

        $this->assertSame($token, $result);
    }

    public function testRefreshWithProvidedTtl(): void
    {
        $key = new Key('lock-seal-test');
        $token = new SymfonyLockToken($key);

        $this->mockFactory->expects($this->once())
            ->method('createLockFromKey')
            ->with($key, 600.0)
            ->willReturn($this->mockLock);

        $this->mockLock->expects($this->once())
            ->method('refresh')
            ->with(600.0);

        $result = $this->seal->refresh($token, 600.0);

        $this->assertSame($token, $result);
    }

    public function testRefreshWithInvalidTokenTypeThrowsException(): void
    {
        $invalidToken = $this->createMock(SealToken::class);
        $invalidToken->method('__toString')->willReturn('invalid-token');

        $this->expectException(LeaseExpiredException::class);

        $this->seal->refresh($invalidToken);
    }

    public function testRefreshThrowsOnLockExpiredException(): void
    {
        $key = new Key('lock-seal-test');
        $token = new SymfonyLockToken($key);

        $this->mockFactory->expects($this->once())
            ->method('createLockFromKey')
            ->willReturn($this->mockLock);

        $this->mockLock->expects($this->once())
            ->method('refresh')
            ->willThrowException(new LockExpiredException('Lock expired'));

        $this->expectException(LeaseExpiredException::class);

        $this->seal->refresh($token);
    }

    public function testRefreshThrowsOnLockConflictedException(): void
    {
        $key = new Key('lock-seal-test');
        $token = new SymfonyLockToken($key);

        $this->mockFactory->expects($this->once())
            ->method('createLockFromKey')
            ->willReturn($this->mockLock);

        $this->mockLock->expects($this->once())
            ->method('refresh')
            ->willThrowException(new LockConflictedException('Lock conflict'));

        $this->expectException(LeaseExpiredException::class);

        $this->seal->refresh($token);
    }

    public function testRefreshThrowsOnUnexpectedError(): void
    {
        $key = new Key('lock-seal-test');
        $token = new SymfonyLockToken($key);

        $this->mockFactory->expects($this->once())
            ->method('createLockFromKey')
            ->willReturn($this->mockLock);

        $this->mockLock->expects($this->once())
            ->method('refresh')
            ->willThrowException(new \RuntimeException('Something went wrong'));

        $this->expectException(LeaseExpiredException::class);

        $this->seal->refresh($token);
    }

    public function testIsExpiredReturnsTrue(): void
    {
        $key = new Key('lock-seal-test');
        $token = new SymfonyLockToken($key);

        $this->mockFactory->expects($this->once())
            ->method('createLockFromKey')
            ->willReturn($this->mockLock);

        $this->mockLock->expects($this->once())
            ->method('isExpired')
            ->willReturn(true);

        $this->assertTrue($this->seal->isExpired($token));
    }

    public function testIsExpiredReturnsFalse(): void
    {
        $key = new Key('lock-seal-test');
        $token = new SymfonyLockToken($key);

        $this->mockFactory->expects($this->once())
            ->method('createLockFromKey')
            ->willReturn($this->mockLock);

        $this->mockLock->expects($this->once())
            ->method('isExpired')
            ->willReturn(false);

        $this->assertFalse($this->seal->isExpired($token));
    }

    public function testGetRemainingLifetime(): void
    {
        $key = new Key('lock-seal-test');
        $token = new SymfonyLockToken($key);

        $this->mockFactory->expects($this->once())
            ->method('createLockFromKey')
            ->willReturn($this->mockLock);

        $this->mockLock->expects($this->once())
            ->method('getRemainingLifetime')
            ->willReturn(150.5);

        $this->assertSame(150.5, $this->seal->getRemainingLifetime($token));
    }

    public function testGetRemainingLifetimeReturnsNull(): void
    {
        $key = new Key('lock-seal-test');
        $token = new SymfonyLockToken($key);

        $this->mockFactory->expects($this->once())
            ->method('createLockFromKey')
            ->willReturn($this->mockLock);

        $this->mockLock->expects($this->once())
            ->method('getRemainingLifetime')
            ->willReturn(null);

        $this->assertNull($this->seal->getRemainingLifetime($token));
    }

    public function testToString(): void
    {
        $this->assertSame('lock-seal-test', (string) $this->seal);
    }
}
