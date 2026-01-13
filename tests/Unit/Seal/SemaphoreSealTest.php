<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Tests\Unit\Seal;

use Clegginabox\Airlock\Seal\SemaphoreSeal;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Semaphore\Exception\SemaphoreReleasingException;
use Symfony\Component\Semaphore\Key;
use Symfony\Component\Semaphore\PersistingStoreInterface;
use Symfony\Component\Semaphore\SemaphoreFactory;

final class SemaphoreSealTest extends TestCase
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

        $factory = new SemaphoreFactory($store);
        $seal = new SemaphoreSeal($factory, 'test_resource', 1, 1, $configuredTtl);

        $key = new Key('test_resource', 1);
        $token = serialize($key);

        $seal->refresh($token);
    }

    public function testReleaseBubblesExceptionsInsteadOfSwallowing(): void
    {
        $store = $this->createMock(PersistingStoreInterface::class);

        $key = new Key('res', 1);

        $store->expects($this->once())
            ->method('delete')
            ->willThrowException(new SemaphoreReleasingException($key, 'Connection failed'));

        $factory = new SemaphoreFactory($store);
        $seal = new SemaphoreSeal($factory, 'res', 1);

        $token = serialize($key);

        $this->expectException(SemaphoreReleasingException::class);
        $seal->release($token);
    }
}
