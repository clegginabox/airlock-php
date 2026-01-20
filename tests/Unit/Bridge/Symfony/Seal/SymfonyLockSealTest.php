<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Tests\Unit\Bridge\Symfony\Seal;

use Clegginabox\Airlock\Bridge\Symfony\Seal\SymfonyLockSeal;
use Clegginabox\Airlock\Bridge\Symfony\Seal\SymfonyLockToken;
use PHPUnit\Framework\TestCase;
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
}
