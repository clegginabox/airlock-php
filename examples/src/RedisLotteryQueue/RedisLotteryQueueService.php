<?php

declare(strict_types=1);

namespace App\RedisLotteryQueue;

use App\Factory\AirlockFactory;
use Clegginabox\Airlock\Bridge\Symfony\Seal\SymfonySemaphoreToken;
use Clegginabox\Airlock\EntryResult;
use Clegginabox\Airlock\QueueAirlock;
use Symfony\Component\Mercure\Jwt\LcobucciFactory;
use Symfony\Component\Semaphore\Key;

final class RedisLotteryQueueService
{
    private QueueAirlock $airlock;

    public function __construct(
        private readonly AirlockFactory $airlockFactory,
    ) {
        $this->airlock = $this->airlockFactory->redisLotteryQueueWithMercure(
            limit: 1, // Only allow one person "inside" at a time
            ttl: 60, // 60 second time limit
            claimWindow: 10, // 10 seconds to claim the place
        );
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

    public function getTopic(string $clientId): string
    {
        return $this->airlock->getTopic($clientId);
    }

    public function getHubUrl(): string
    {
        return getenv('MERCURE_PUBLIC_URL') ?: 'http://localhost:3000/.well-known/mercure';
    }

    public function getSubscriberToken(string $clientId): string
    {
        $jwtSecret = getenv('MERCURE_JWT_SECRET') ?: 'airlock-mercure-secret-32chars-minimum';

        return new LcobucciFactory($jwtSecret)
            ->create(subscribe: [$this->getTopic($clientId)]);
    }
}
