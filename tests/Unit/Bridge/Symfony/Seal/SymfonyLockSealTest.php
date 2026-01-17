<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Tests\Unit\Bridge\Symfony\Seal;

use Clegginabox\Airlock\Bridge\Symfony\Seal\SymfonyLockSeal;
use Clegginabox\Airlock\Bridge\Symfony\Seal\SymfonyLockToken;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

class SymfonyLockSealTest extends TestCase
{
    private SymfonyLockSeal $seal;

    protected function setUp(): void
    {
        $store = new FlockStore();

        $this->seal = new SymfonyLockSeal(
            new LockFactory($store),
            'lock-seal-test',
            300,
            false
        );
    }

    public function testItAcquiresLock(): void
    {
        $token = $this->seal->tryAcquire();

        $this->assertInstanceof(SymfonyLockToken::class, $token);
        $this->assertTrue($this->seal->isAcquired($token));
    }

    public function testItReleasesLock(): void
    {
        $token = $this->seal->tryAcquire();
        $this->assertInstanceof(SymfonyLockToken::class, $token);

        $this->seal->release($token);
    }
}
