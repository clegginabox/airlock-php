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

        // 1. Setup: Mock the Store (The engine behind the Semaphore)
        $store = $this->createMock(PersistingStoreInterface::class);

        // 2. Expectation: The Store should receive the configured TTL (50.0), NOT null.
        // THIS WILL FAIL: The bug sends 'null' (or causes default behavior), we expect 50.0
        $store->expects($this->once())
            ->method('putOffExpiration')
            ->with(
                $this->isInstanceOf(Key::class),
                50.0
            );

        // 3. Use a REAL Factory with our MOCK Store
        $factory = new SemaphoreFactory($store);

        // Configure Seal with 50s TTL
        $seal = new SemaphoreSeal($factory, 'test_resource', 1, 1, $configuredTtl);

        // 4. Create a valid token to refresh
        // We need a real Key to serialize
        $key = new Key('test_resource', 1);
        $token = serialize($key);

        // 5. Action: Call refresh without args
        $seal->refresh($token);
    }

    public function testReleaseBubblesExceptionsInsteadOfSwallowing(): void
    {
        $store = $this->createMock(PersistingStoreInterface::class);

        $key = new Key('res', 1);

        // 1. Setup: The Store throws an exception when asked to delete the key
        $store->method('delete')
            ->willThrowException(new SemaphoreReleasingException($key, 'Connection failed'));

        // 2. Use Real Factory
        $factory = new SemaphoreFactory($store);
        $seal = new SemaphoreSeal($factory, 'res', 1);

        $token = serialize($key);

        // 3. Expectation: The exception should bubble up
        // THIS WILL FAIL: The current code catches this exception and returns void
        $this->expectException(SemaphoreReleasingException::class);

        $seal->release($token);
    }
}
