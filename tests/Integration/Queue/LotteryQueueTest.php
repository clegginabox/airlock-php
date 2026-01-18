<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Tests\Integration\Queue;

use Clegginabox\Airlock\Queue\LotteryQueue;
use Clegginabox\Airlock\Queue\Storage\Lottery\Redis\RedisLotteryQueueStore;
use Clegginabox\Airlock\Tests\Factory\RedisFactory;
use PHPUnit\Framework\TestCase;
use Redis;

class LotteryQueueTest extends TestCase
{
    private Redis $redis;

    private LotteryQueue $queue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->redis = RedisFactory::create();
        $this->redis->flushAll();

        $storage = new RedisLotteryQueueStore(
            $this->redis,
            'test:queue:list',
            'test:queue:set'
        );

        $this->queue = new LotteryQueue($storage);
    }

    public function testPeekAlwaysReturnsPoolMember(): void
    {
        $this->queue->add('user_A');
        $this->queue->add('user_B');
        $this->queue->add('user_C');

        for ($i = 0; $i < 20; $i++) {
            $this->redis->del('test:queue:set:candidate');
            $this->assertContains($this->queue->peek(), ['user_A', 'user_B', 'user_C']);
        }
    }

    public function testAddIsIdempotent(): void
    {
        // Add User A twice
        $pos1 = $this->queue->add('user_A');
        $pos2 = $this->queue->add('user_A');

        // The list length should still be 1
        $this->assertSame(1, $pos1);
        $this->assertSame(1, $pos2);
    }

    public function testRemove(): void
    {
        // Add 3 users
        $this->queue->add('user_A');
        $this->queue->add('user_B');
        $this->queue->add('user_C');

        $this->queue->remove('user_B');

        $this->assertContains($this->queue->peek(), ['user_A', 'user_C']);
        $this->assertNotEquals('user_B', $this->queue->peek());
    }
}
