<?php

declare(strict_types=1);

namespace App\Examples\RedisLotteryQueue;

use App\Factory\AirlockFactory;
use Clegginabox\Airlock\Bridge\Symfony\Seal\SymfonySemaphoreToken;
use Clegginabox\Airlock\Decorator\EventDispatchingAirlock;
use Clegginabox\Airlock\EntryResult;
use Symfony\Component\Mercure\Jwt\LcobucciFactory;
use Symfony\Component\Semaphore\Key;

final class RedisLotteryQueueService
{
    private EventDispatchingAirlock $airlock;

    public function __construct(private readonly AirlockFactory $airlockFactory)
    {
        $this->airlock = $this->airlockFactory->redisLotteryQueueWithMercure();
    }

    public function start(string $clientId): EntryResult
    {
        return $this->airlock->enter($clientId);
    }

    public function release(string $serializedToken): void
    {
        $key = unserialize($serializedToken, ['allowed_classes' => [Key::class]]);
        if (!$key instanceof Key) {
            return;
        }

        $this->airlock->release(new SymfonySemaphoreToken($key));
    }

    public function getPosition(string $clientId): ?int
    {
        return $this->airlock->getPosition($clientId);
    }

    public function getTopic(string $clientId): string
    {
        return $this->airlock->getTopic($clientId);
    }

    public function getHubUrl(): string
    {
        return getenv('MERCURE_PUBLIC_URL') ?: 'http://localhost/.well-known/mercure';
    }

    public function getSubscriberToken(string $clientId): string
    {
        $jwtSecret = getenv('MERCURE_JWT_SECRET') ?: 'airlock-mercure-secret-32chars-minimum';

        return new LcobucciFactory($jwtSecret)
            ->create(subscribe: [$this->getTopic($clientId)]);
    }

    public function getGlobalToken(): string
    {
        $jwtSecret = getenv('MERCURE_JWT_SECRET') ?: 'airlock-mercure-secret-32chars-minimum';

        return new LcobucciFactory($jwtSecret)
            ->create(subscribe: [RedisLotteryQueue::NAME->value]);
    }
}
