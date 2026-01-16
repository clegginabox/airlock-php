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
    public function testAcquire(): void
    {
        $store = $this->createMock(PersistingStoreInterface::class);
        $store->expects($this->once())
            ->method('save')
            ->with(
                $this->isInstanceOf(Key::class),
                1.0
            );

        $seal = new SymfonySemaphoreSeal(
            new SemaphoreFactory($store),
            'test_resource',
            1,
            1,
            1.0
        );

        $token = $seal->tryAcquire();
        $this->assertNotNull($token);
    }

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

        $seal = new SymfonySemaphoreSeal(new SemaphoreFactory($store), 'res', 1);
        $token = $seal->tryAcquire();

        $store->expects($this->once())
            ->method('delete')
            ->willThrowException(new SemaphoreReleasingException($token->getKey(), 'Connection failed'));

        $this->expectException(SemaphoreReleasingException::class);
        $seal->release($token);
    }
}
