<?php

declare(strict_types=1);

namespace App\Factory;

use App\GlobalLock\GlobalLock;
use App\RedisLotteryQueue\RedisLotteryQueue;
use Clegginabox\Airlock\Bridge\Mercure\MercureAirlockNotifier;
use Clegginabox\Airlock\Bridge\Symfony\Mercure\SymfonyMercureHubFactory;
use Clegginabox\Airlock\Bridge\Symfony\Seal\SymfonyLockSeal;
use Clegginabox\Airlock\Bridge\Symfony\Seal\SymfonySemaphoreSeal;
use Clegginabox\Airlock\Notifier\NullAirlockNotifier;
use Clegginabox\Airlock\OpportunisticAirlock;
use Clegginabox\Airlock\Queue\LotteryQueue;
use Clegginabox\Airlock\Queue\Storage\Lottery\RedisLotteryQueueStore;
use Clegginabox\Airlock\QueueAirlock;
use Redis;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\RedisStore as LockRedisStore;
use Symfony\Component\Semaphore\SemaphoreFactory;
use Symfony\Component\Semaphore\Store\RedisStore as SemaphoreRedisStore;

/**
 * In a framework you'd configure these factory methods in a DI container.
 */
#[Autoconfigure(shared: false)]
class AirlockFactory
{
    public function __construct(private Redis $redis)
    {
    }

    /**
     * Global Lock Example
     *
     * Seal: Redis backed Symfony Lock
     * Queue: None
     * Airlock: OpportunisticAirlock
     */
    public function globalLock(int $ttl = 10): OpportunisticAirlock
    {
        $seal = new SymfonyLockSeal(
            new LockFactory(new LockRedisStore($this->redis)),
            GlobalLock::RESOURCE->value,
            $ttl,
            false,
        );

        return new OpportunisticAirlock($seal);
    }

    /**
     * Redis Lottery Queue Example
     *
     * Seal: Redis backed Symfony Semaphore
     * Queue: Redis backed Lottery Queue
     * Airlock: QueueAirlock
     */
    public function redisLotteryQueue(
        int $limit = 3,
        int $ttl = 60,
        int $claimWindow = 10,
    ): QueueAirlock {
        $seal = new SymfonySemaphoreSeal(
            factory: new SemaphoreFactory(new SemaphoreRedisStore($this->redis)),
            resource: RedisLotteryQueue::RESOURCE->value,
            limit: $limit,
            weight: 1,
            ttlInSeconds: $ttl,
            autoRelease: false,
        );

        $queue = new LotteryQueue(
            new RedisLotteryQueueStore(
                redis: $this->redis,
                setKey: RedisLotteryQueue::SET_KEY->value,
                candidateKey: RedisLotteryQueue::CANDIDATE_KEY->value,
                candidateTtlSeconds: $claimWindow,
            ),
        );

        return new QueueAirlock($seal, $queue, new NullAirlockNotifier());
    }

    public function redisLotteryQueueWithMercure(
        int $limit = 3,
        int $ttl = 60,
        int $claimWindow = 10,
    ): QueueAirlock {
        $seal = new SymfonySemaphoreSeal(
            factory: new SemaphoreFactory(new SemaphoreRedisStore($this->redis)),
            resource: RedisLotteryQueue::RESOURCE->value,
            limit: $limit,
            weight: 1,
            ttlInSeconds: $ttl,
            autoRelease: false,
        );

        $queue = new LotteryQueue(
            new RedisLotteryQueueStore(
                redis: $this->redis,
                setKey: RedisLotteryQueue::SET_KEY->value,
                candidateKey: RedisLotteryQueue::CANDIDATE_KEY->value,
                candidateTtlSeconds: $claimWindow,
            ),
        );

        $hubUrl = getenv('MERCURE_HUB_URL') ?: 'http://localhost/.well-known/mercure';
        $jwtSecret = getenv('MERCURE_JWT_SECRET') ?: 'airlock-mercure-secret-32chars-minimum';
        $hub = SymfonyMercureHubFactory::create($hubUrl, $jwtSecret);

        return new QueueAirlock($seal, $queue, new MercureAirlockNotifier($hub));
    }
}
