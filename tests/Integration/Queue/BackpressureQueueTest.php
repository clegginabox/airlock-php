<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Tests\Integration\Queue;

use Clegginabox\Airlock\HealthCheckerInterface;
use Clegginabox\Airlock\Queue\BackpressureQueue;
use Clegginabox\Airlock\Queue\RedisFifoQueue;
use Clegginabox\Airlock\Tests\Factory\RedisFactory;
use PHPUnit\Framework\TestCase;
use Redis;

class BackpressureQueueTest extends TestCase
{
    private Redis $redis;

    private RedisFifoQueue $fifoQueue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->redis = RedisFactory::create();
        $this->redis->flushAll();

        $this->fifoQueue = new RedisFifoQueue(
            $this->redis,
            'test:queue:list',
            'test:queue:set'
        );
    }

    public function testItAllowsPeekWhenHealthIsGood(): void
    {
        $healthCheck = new class implements HealthCheckerInterface {
            public function getScore(): float
            {
                return 1;
            }
        };

        $backpressureQueue = new BackpressureQueue(
            $this->fifoQueue,
            $healthCheck,
            0.5
        );

        $backpressureQueue->add('user_A');
        $this->assertSame('user_A', $backpressureQueue->peek());
    }

    public function testPeekReturnsNullWhenHealthIsBad(): void
    {
        $healthCheck = new class implements HealthCheckerInterface {
            public function getScore(): float
            {
                return 0;
            }
        };

        $backpressureQueue = new BackpressureQueue(
            $this->fifoQueue,
            $healthCheck,
            0.5
        );

        $backpressureQueue->add('user_A');
        $this->assertNull($backpressureQueue->peek());
    }

    /**
     * At threshold = allow entry
     */
    public function testPeekAtExactThreshold(): void
    {
        $healthCheck = new class implements HealthCheckerInterface {
            public function getScore(): float
            {
                return 0.5;
            }
        };

        $backpressureQueue = new BackpressureQueue(
            $this->fifoQueue,
            $healthCheck,
            0.5
        );

        $backpressureQueue->add('user_A');
        $this->assertEquals('user_A', $backpressureQueue->peek());
    }

    public function testAddAndRemoveStillDelegate(): void
    {
        $healthCheck = new class implements HealthCheckerInterface {
            public function getScore(): float
            {
                return 1;
            }
        };

        $backpressureQueue = new BackpressureQueue(
            $this->fifoQueue,
            $healthCheck,
            0.5
        );

        $backpressureQueue->add('user_A');
        $backpressureQueue->add('user_B');
        $backpressureQueue->add('user_C');

        $backpressureQueue->remove('user_A');
        $this->assertSame('user_B', $backpressureQueue->peek());
    }

    public function testHealthIsCheckedOnEveryPeek(): void
    {
        $healthCheck = new class implements HealthCheckerInterface {
            public float $score = 1.0;

            public function getScore(): float
            {
                return $this->score;
            }
        };

        $backpressureQueue = new BackpressureQueue(
            $this->fifoQueue,
            $healthCheck,
            0.5
        );

        $backpressureQueue->add('user_A');

        $this->assertSame('user_A', $backpressureQueue->peek());

        $healthCheck->score = 0.0;
        $this->assertNull($backpressureQueue->peek());
    }
}
