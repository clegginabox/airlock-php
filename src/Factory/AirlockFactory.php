<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Factory;

use Clegginabox\Airlock\AirlockInterface;
use Clegginabox\Airlock\Notifier\AirlockNotifierInterface;
use Clegginabox\Airlock\Notifier\NullAirlockNotifier;
use Clegginabox\Airlock\OpportunisticAirlock;
use Clegginabox\Airlock\Queue\RedisFifoQueue;
use Clegginabox\Airlock\Queue\RedisLotteryQueue;
use Clegginabox\Airlock\QueueAirlock;
use Clegginabox\Airlock\Seal\LockSeal;
use Clegginabox\Airlock\Seal\SemaphoreSeal;
use Redis;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\RedisStore as LockRedisStore;
use Symfony\Component\Semaphore\SemaphoreFactory;
use Symfony\Component\Semaphore\Store\RedisStore as SemaphoreRedisStore;

final readonly class AirlockFactory
{
    public static function fairWaitingRoom(
        Redis $redis,
        int $limit,
        int $ttl,
        ?AirlockNotifierInterface $notifier = null,
    ): AirlockInterface {
        return new QueueAirlock(
            seal: new SemaphoreSeal(
                factory: new SemaphoreFactory(
                    new SemaphoreRedisStore($redis),
                ),
                limit: $limit,
                ttlInSeconds: $ttl,
            ),
            queue: new RedisFifoQueue($redis),
            notifier: $notifier ?? new NullAirlockNotifier(),
        );
    }

    public static function lotteryWaitingRoom(
        Redis $redis,
        int $limit,
        int $ttl,
        ?AirlockNotifierInterface $notifier = null,
    ): AirlockInterface {
        return new QueueAirlock(
            seal: new SemaphoreSeal(
                factory: new SemaphoreFactory(
                    new SemaphoreRedisStore($redis),
                ),
                limit: $limit,
                ttlInSeconds: $ttl,
            ),
            queue: new RedisLotteryQueue($redis),
            notifier: $notifier ?? new NullAirlockNotifier(),
        );
    }

    public static function antiHug(Redis $redis, string $resource, int $limit, int $ttl): AirlockInterface
    {
        return new OpportunisticAirlock(
            new SemaphoreSeal(
                factory: new SemaphoreFactory(
                    new SemaphoreRedisStore($redis),
                ),
                resource: $resource,
                limit: $limit,
                ttlInSeconds: $ttl,
            ),
        );
    }

    public static function singleton(Redis $redis, string $resource, int $ttl): AirlockInterface
    {
        return new OpportunisticAirlock(
            new LockSeal(
                factory: new LockFactory(
                    new LockRedisStore($redis),
                ),
                resource: $resource,
                ttlInSeconds: $ttl,
            ),
        );
    }
}
