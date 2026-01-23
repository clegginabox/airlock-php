<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Tests\Integration\Bridge\Symfony;

use Clegginabox\Airlock\Bridge\Symfony\Seal\SymfonySemaphoreSeal;
use Clegginabox\Airlock\Exception\LeaseExpiredException;
use Clegginabox\Airlock\Seal\SealToken;
use Clegginabox\Airlock\Tests\Factory\RedisFactory;
use PHPUnit\Framework\TestCase;
use Redis;
use Symfony\Component\Semaphore\SemaphoreFactory;
use Symfony\Component\Semaphore\Store\RedisStore;

class SymfonySemaphoreSealTest extends TestCase
{
    private Redis $redis;

    private SymfonySemaphoreSeal $constraint;

    protected function setUp(): void
    {
        parent::setUp();

        $this->redis = RedisFactory::create();
        $this->redis->flushAll();

        $store = new RedisStore($this->redis);
        $factory = new SemaphoreFactory($store);

        $this->constraint = new SymfonySemaphoreSeal(
            $factory,
            'test_room',
            limit: 2,
            ttlInSeconds: 10
        );
    }

    public function testItRespectsCapacityLimit(): void
    {
        // 1. Acquire first slot
        $token1 = $this->constraint->tryAcquire();
        $this->assertNotNull($token1, 'Should acquire 1st slot');
        $this->assertTrue($this->constraint->isAcquired($token1));

        // 2. Acquire second slot
        $token2 = $this->constraint->tryAcquire();
        $this->assertNotNull($token2, 'Should acquire 2nd slot');

        // 3. Try third slot -> Should FAIL (returns null)
        $token3 = $this->constraint->tryAcquire();
        $this->assertNull($token3, 'Should not acquire 3rd slot');
    }

    public function testReleaseOpensSlot(): void
    {
        // Fill capacity (2 slots)
        $token1 = $this->constraint->tryAcquire();
        $this->assertInstanceOf(SealToken::class, $token1);
        $this->constraint->tryAcquire();

        // Verify full
        $token2 = $this->constraint->tryAcquire();
        $this->assertNull($token2);

        // Release one
        $this->constraint->release($token1);
        $this->assertFalse($this->constraint->isAcquired($token1));

        // Attempt acquire again -> Should Succeed now
        $token3 = $this->constraint->tryAcquire();
        $this->assertNotNull($token3, 'Should acquire slot after release');
    }

    public function testTtlExpiry(): void
    {
        // Create a constraint with 1 second TTL
        $shortFactory = new SemaphoreFactory(new RedisStore($this->redis));
        $shortConstraint = new SymfonySemaphoreSeal(
            $shortFactory,
            'short_room',
            limit: 1,
            ttlInSeconds: 1
        );

        $token = $shortConstraint->tryAcquire();
        $this->assertNotNull($token);

        // Wait for TTL to pass (1.1s)
        usleep(1_100_000);

        // Should be expired now
        $this->assertTrue($shortConstraint->isExpired($token));

        // Another user should be able to steal the slot now
        // Note: Symfony Semaphore lazy-expires on the next acquire attempt usually
        $newToken = $shortConstraint->tryAcquire();
        $this->assertNotNull($newToken, 'Should take over the expired slot');
    }

    public function testRefreshThrowsLeaseExpiredExceptionOnFailure(): void
    {
        // 1. Create a constraint with a very short TTL (1 second)
        $shortConstraint = new SymfonySemaphoreSeal(
            new SemaphoreFactory(new RedisStore($this->redis)),
            'fast_expire_room',
            limit: 1,
            ttlInSeconds: 1
        );

        // Acquire a token
        $token = $shortConstraint->tryAcquire();
        $this->assertNotNull($token);

        // Wipe the DB
        $this->redis->flushDB();

        $this->expectException(LeaseExpiredException::class);
        $this->expectExceptionMessage('The lease'); // partial match on your message

        $shortConstraint->refresh($token);
    }

    public function testRefreshExtendsLeaseAndReturnsNewToken(): void
    {
        $constraint = new SymfonySemaphoreSeal(
            new SemaphoreFactory(new RedisStore($this->redis)),
            'refresh_test_room',
            limit: 1,
            ttlInSeconds: 2
        );

        $oldToken = $constraint->tryAcquire();
        $this->assertNotNull($oldToken);

        // Sleep 1s (halfway through life)
        sleep(1);

        // Refresh for another 10 seconds
        $newToken = $constraint->refresh($oldToken, 10);

        $this->assertNotSame($oldToken, $newToken, 'Token string must change because timestamp changed');
        $this->assertTrue($constraint->isAcquired($newToken), 'Refreshing should not auto-release the lease');
        $this->assertTrue($constraint->getRemainingLifetime($newToken) > 5.0);
    }
}
