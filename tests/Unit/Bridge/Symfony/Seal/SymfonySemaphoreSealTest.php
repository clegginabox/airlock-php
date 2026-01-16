<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Tests\Unit\Bridge\Symfony\Seal;

use Clegginabox\Airlock\Bridge\Symfony\Seal\SymfonySemaphoreSeal;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Semaphore\Exception\SemaphoreReleasingException;
use Symfony\Component\Semaphore\Key;
use Symfony\Component\Semaphore\PersistingStoreInterface;
use Symfony\Component\Semaphore\SemaphoreFactory;

final class SymfonySemaphoreSealTest extends TestCase
{
    public function testRefreshUsesClassConfiguredTtlWhenArgumentIsNull(): void
    {
        $configuredTtl = 50.0;

        $store = $this->createMock(PersistingStoreInterface::class);
        $store->expects($this->once())
            ->method('putOffExpiration')
            ->with(
                $this->isInstanceOf(Key::class),
                50.0
            );

        $seal = new SymfonySemaphoreSeal(
            new SemaphoreFactory($store),
            'test_resource',
            1,
            1,
            $configuredTtl
        );

        $token = $seal->tryAcquire();
        $seal->refresh($token);
    }

    public function testReleaseBubblesExceptionsInsteadOfSwallowing(): void
    {
        $store = $this->createMock(PersistingStoreInterface::class);

        $key = new Key('res', 1);

        $store->expects($this->once())
            ->method('delete')
            ->willThrowException(new SemaphoreReleasingException($key, 'Connection failed'));

        $seal = new SymfonySemaphoreSeal(new SemaphoreFactory($store), 'res', 1);
        $token = $seal->tryAcquire();

        $this->expectException(SemaphoreReleasingException::class);
        $seal->release($token);
    }
}
