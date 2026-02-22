<?php

declare(strict_types=1);

namespace App\Factory;

use App\Examples\GlobalLock\GlobalLock;
use App\Examples\RedisFifoQueue\RedisFifoQueue;
use App\Examples\RedisLotteryQueue\RedisLotteryQueue;
use App\Examples\TrafficControl\TrafficControl;
use Clegginabox\Airlock\Airlock;
use Clegginabox\Airlock\Bridge\Symfony\Seal\SymfonyLockSeal;
use Clegginabox\Airlock\Bridge\Symfony\Seal\SymfonyRateLimiterSeal;
use Clegginabox\Airlock\Bridge\Symfony\Seal\SymfonySemaphoreSeal;
use Clegginabox\Airlock\Decorator\EventDispatchingAirlock;
use Clegginabox\Airlock\Decorator\LoggingAirlock;
use Clegginabox\Airlock\OpportunisticAirlock;
use Clegginabox\Airlock\Queue\FifoQueue;
use Clegginabox\Airlock\Queue\LotteryQueue;
use Clegginabox\Airlock\Queue\Storage\Fifo\RedisFifoQueueStore;
use Clegginabox\Airlock\Queue\Storage\Lottery\RedisLotteryQueueStore;
use Clegginabox\Airlock\QueueAirlock;
use Clegginabox\Airlock\RateLimitingAirlock;
use Clegginabox\Airlock\Reservation\RedisReservationStore;
use Clegginabox\Airlock\Seal\CompositeSeal;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Redis;
use Spiral\RoadRunner\Metrics\MetricsInterface;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\RedisStore as LockRedisStore;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Symfony\Component\Semaphore\SemaphoreFactory;
use Symfony\Component\Semaphore\Store\RedisStore as SemaphoreRedisStore;

/**
 * In a framework you'd configure these factory methods in a DI container.
 */
class AirlockFactory
{
    private const int REDIS_LOTTERY_DEFAULT_LIMIT = 1;
    private const int REDIS_LOTTERY_DEFAULT_TTL = 10;
    private const int REDIS_LOTTERY_DEFAULT_CLAIM_WINDOW = 5;

    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
        private MetricsInterface $metrics,
        private readonly LoggerInterface $logger,
        private Redis $redis,
    ) {
    }

    /**
     * Traffic Control Example
     *
     * Seal: Redis backed Symfony Lock (one per provider)
     * Queue: None
     * Airlock: OpportunisticAirlock
     */
    public function trafficControl(string $provider, int $ttl = 10): Airlock
    {
        $resource = match ($provider) {
            'alpha' => TrafficControl::RESOURCE_ALPHA->value,
            'beta' => TrafficControl::RESOURCE_BETA->value,
            'gamma' => TrafficControl::RESOURCE_GAMMA->value,
            default => throw new \InvalidArgumentException("Unknown provider: {$provider}"),
        };

        $storage = new CacheStorage(new RedisAdapter($this->redis));

        $thirtyPerMinuteLimit = new RateLimiterFactory([
            'policy' => 'fixed_window',
            'interval' => '1 minute',
            'limit' => 30,
            'id' => 'thirty-per-minute-limit',
        ], $storage);

        $fiftyPerMinuteLimit = new RateLimiterFactory([
            'policy' => 'fixed_window',
            'interval' => '1 minute',
            'limit' => 50,
            'id' => 'fifty-per-minute-limit',
        ], $storage);

        // Alpha - 50 RPM
        if ($resource === TrafficControl::RESOURCE_ALPHA->value) {
            return new RateLimitingAirlock(
                new SymfonyRateLimiterSeal($fiftyPerMinuteLimit->create(TrafficControl::RESOURCE_ALPHA->value))
            );
        }

        // Beta - 50 RPM, 2 concurrent requests
        if ($resource === TrafficControl::RESOURCE_BETA->value) {
            $seal = new CompositeSeal(
                new SymfonySemaphoreSeal(
                    new SemaphoreFactory(new SemaphoreRedisStore($this->redis)),
                    resource: $resource,
                    limit: 5,
                    ttlInSeconds: $ttl
                ),
                new SymfonyRateLimiterSeal($fiftyPerMinuteLimit->create(TrafficControl::RESOURCE_BETA->value))
            );

            return new OpportunisticAirlock($seal);
        }

        // Gamma - 30 RPM
        return new RateLimitingAirlock(
            new SymfonyRateLimiterSeal($thirtyPerMinuteLimit->create(TrafficControl::RESOURCE_GAMMA->value))
        );
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
        int $limit = self::REDIS_LOTTERY_DEFAULT_LIMIT,
        int $ttl = self::REDIS_LOTTERY_DEFAULT_TTL,
        int $claimWindow = self::REDIS_LOTTERY_DEFAULT_CLAIM_WINDOW,
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

        $reservations = new RedisReservationStore(
            redis: $this->redis,
            keyPrefix: RedisLotteryQueue::RESERVATION_KEY_PREFIX->value,
        );

        return new QueueAirlock(
            $seal,
            $queue,
            topicPrefix: '/redis-lottery-queue',
            reservations: $reservations,
        );
    }

    public function redisLotteryQueueWithMercure(
        int $limit = self::REDIS_LOTTERY_DEFAULT_LIMIT,
        int $ttl = self::REDIS_LOTTERY_DEFAULT_TTL,
        int $claimWindow = self::REDIS_LOTTERY_DEFAULT_CLAIM_WINDOW,
    ): EventDispatchingAirlock {
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

        $reservations = new RedisReservationStore(
            redis: $this->redis,
            keyPrefix: RedisLotteryQueue::RESERVATION_KEY_PREFIX->value,
        );

        $loggingDecorator = new LoggingAirlock(
            new QueueAirlock(
                $seal,
                $queue,
                topicPrefix: '/redis-lottery-queue',
                reservations: $reservations,
            ),
            $this->logger,
        );

        return new EventDispatchingAirlock(
            $loggingDecorator,
            $this->dispatcher,
            RedisLotteryQueue::NAME->value,
        );
    }

    public function redisFifoQueue(): QueueAirlock
    {
        $seal = new SymfonySemaphoreSeal(
            factory: new SemaphoreFactory(new SemaphoreRedisStore($this->redis)),
            resource: RedisLotteryQueue::RESOURCE->value,
            limit: 3,
            weight: 1,
            ttlInSeconds: 60,
            autoRelease: false,
        );

        $queue = new FifoQueue(
            new RedisFifoQueueStore(
                redis: $this->redis,
                listKey: RedisFifoQueue::LIST_KEY->value,
                setKey: RedisFifoQueue::SET_KEY->value,
            )
        );

        $reservations = new RedisReservationStore(
            redis: $this->redis,
            keyPrefix: RedisFifoQueue::RESERVATION_KEY_PREFIX->value,
        );

        return new QueueAirlock(
            seal: $seal,
            queue: $queue,
            topicPrefix: RedisFifoQueue::NAME->value,
            reservations: $reservations,
        );
    }

    public function redisFifoQueueWithMercure(): EventDispatchingAirlock
    {
        $airlock = $this->redisFifoQueue();

        $loggingDecorator = new LoggingAirlock(
            inner: $airlock,
            logger: $this->logger,
        );

        return new EventDispatchingAirlock(
            inner: $loggingDecorator,
            dispatcher: $this->dispatcher,
            airlockIdentifier: RedisFifoQueue::NAME->value,
        );
    }
}
