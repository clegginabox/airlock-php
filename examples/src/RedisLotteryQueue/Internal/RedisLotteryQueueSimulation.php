<?php

declare(strict_types=1);

namespace App\RedisLotteryQueue\Internal;

use App\Factory\AirlockFactory;
use App\Infrastructure\JobQueue;
use App\RedisLotteryQueue\RedisLotteryQueue;
use Clegginabox\Airlock\Seal\SealToken;
use Redis;

/**
 * Internal simulation code only
 *
 * @internal
 */
class RedisLotteryQueueSimulation
{
    private const string SEED_VERSION = 'v1';
    private const int SEED_TTL_SECONDS = 3600;

    public function __construct(
        private readonly AirlockFactory $airlockFactory,
        private readonly Redis $redis,
        private readonly JobQueue $jobs
    ) {
    }

    /**
     * Handle a user winning the queue lottery
     */
    public function onSuccess(?SealToken $token, string $clientId, int $holdSeconds): void
    {
        if (!($token instanceof SealToken)) {
            return;
        }

        $this->jobs->enqueue(RedisLotteryQueue::NAME->value, [
            'action' => 'hold',
            'clientId' => $clientId,
            'serializedKey' => (string) $token,
            'holdSeconds' => $holdSeconds,
        ]);
    }

    /**
     * Pre-seed the queue and room with bots (deterministic, idempotent).
     *
     * - Only runs once per seed version (or until reset clears the key).
     * - Uses stable bot IDs.
     * - Enqueues hold jobs only once.
     */
    public function seedOnce(): void
    {
        $seedKey = sprintf('airlock:examples:%s:seeded:%s', RedisLotteryQueue::NAME->value, self::SEED_VERSION);

        // Set-if-not-exists: if this returns false, we've already seeded.
        if ($this->redis->set($seedKey, '1', ['nx', 'ex' => self::SEED_TTL_SECONDS]) !== true) {
            return;
        }

        $airlock = $this->airlockFactory->redisLotteryQueue();

        // 1) Fill the "room" with bots (deterministic IDs and hold times)
        for ($i = 1; $i <= 3; $i++) {
            $botId = "room-bot-{$i}";

            $result = $airlock->enter($botId);
            $token  = $result->getToken();

            // If already full (e.g. someone seeded manually), skip.
            if ($token === null) {
                continue;
            }

            $this->jobs->enqueue(RedisLotteryQueue::NAME->value, [
                'action' => 'hold',
                'clientId' => $botId,
                'serializedKey' => (string) $token,
                'holdSeconds' => 5 + ($i * 5),
            ]);
        }

        // 2) Add bots to the queue (dead heads)
        for ($i = 1; $i <= 3; $i++) {
            $airlock->enter("queue-bot-{$i}");
        }
    }
}
