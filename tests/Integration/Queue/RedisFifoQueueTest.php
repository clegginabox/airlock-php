<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Tests\Integration\Queue;

use Clegginabox\Airlock\Queue\RedisFifoQueue;
use Clegginabox\Airlock\Tests\Factory\RedisFactory;
use PHPUnit\Framework\TestCase;
use Redis;

class RedisFifoQueueTest extends TestCase
{
    private Redis $redis;
    private RedisFifoQueue $queue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->redis = RedisFactory::create();
        $this->redis->flushAll();

        $this->queue = new RedisFifoQueue(
            $this->redis,
            'test:queue:list',
            'test:queue:set'
        );
    }

    public function testItPreservesFifoOrder(): void
    {
        // Add 3 users
        $this->queue->add('user_A');
        $this->queue->add('user_B');
        $this->queue->add('user_C');

        // Pop them one by one
        $this->assertSame('user_A', $this->queue->pop());
        $this->assertSame('user_B', $this->queue->pop());
        $this->assertSame('user_C', $this->queue->pop());

        // Queue should be empty now
        $this->assertNull($this->queue->pop());
    }

    public function testAddIsIdempotent(): void
    {
        // Add User A twice
        $pos1 = $this->queue->add('user_A');
        $pos2 = $this->queue->add('user_A'); // Should detect duplicate

        // The list length should still be 1
        $this->assertSame(1, $pos1);
        $this->assertSame(1, $pos2); // The position is the same

        // Pop should only return it once
        $this->assertSame('user_A', $this->queue->pop());
        $this->assertNull($this->queue->pop());
    }

    public function testGetPosition(): void
    {
        $this->queue->add('user_A');
        $this->queue->add('user_B');
        $this->queue->add('user_C');

        $this->assertSame(1, $this->queue->getPosition('user_A'));
        $this->assertSame(2, $this->queue->getPosition('user_B'));
        $this->assertSame(3, $this->queue->getPosition('user_C'));

        // Non-existent user
        $this->assertNull($this->queue->getPosition('user_ghost'));
    }

    public function testRemove(): void
    {
        $this->queue->add('user_A');
        $this->queue->add('user_B'); // The one we will remove
        $this->queue->add('user_C');

        $this->queue->remove('user_B');

        // Check positions: User C should shift up?
        // Note: Redis Lists shift automatically when an item is removed.
        $this->assertSame(1, $this->queue->getPosition('user_A'));
        $this->assertSame(2, $this->queue->getPosition('user_C'));

        // Verify pop order
        $this->assertSame('user_A', $this->queue->pop());
        $this->assertSame('user_C', $this->queue->pop());
    }

    public function testConcurrencyIntegrity(): void
    {
        // This simulates the "Race Condition" fix.
        // We manually inject a state where a user is in the SET but not the LIST
        // to verify the Lua script handles it or returns errors gracefully.

        $this->redis->sAdd('test:queue:set', 'zombie_user');

        // If we try to add them properly now, the Lua script sees them in the SET
        // and tries to find them in the LIST.
        // Since they aren't in the LIST, your Lua script returns -1.

        $result = $this->queue->add('zombie_user');
        $this->assertSame(1, $result);
    }

    public function testAddHealsCorruptionInsteadOfReturningNegativeOne(): void
    {
        $queueKey = 'test_corruption_queue';
        $setKey = 'test_corruption_set';

        // 1. Setup: Manually corrupt the state
        // Add user to the SET, but NOT the LIST
        $this->redis->sAdd($setKey, 'ghost_user');
        // Ensure list is empty so we know their "real" position should be 1
        $this->redis->del($queueKey);

        $queue = new RedisFifoQueue($this->redis, $queueKey, $setKey);

        // 2. Action: The ghost user tries to "add" themselves again
        $position = $queue->add('ghost_user');

        // 3. Expectation: The queue should detect the corruption, re-add them to the list,
        // and return a valid position (1).
        // THIS WILL FAIL: currently it returns -1
        $this->assertGreaterThan(0, $position, 'Queue returned invalid position -1, allowing user to skip line.');
        $this->assertEquals(1, $position, 'Queue did not heal the list state.');
    }
}
