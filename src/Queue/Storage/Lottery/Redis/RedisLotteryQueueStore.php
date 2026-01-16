<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Queue\Storage\Lottery\Redis;

use Clegginabox\Airlock\Queue\Storage\Lottery\LotteryQueueStorage;
use Redis;

class RedisLotteryQueueStore implements LotteryQueueStorage
{
    private const string SET_KEY = 'airlock:pool';

    private const string CANDIDATE_KEY = 'airlock:pool:candidate';

    public function __construct(
        private readonly Redis $redis,
        private readonly string $setKey = self::SET_KEY,
        private readonly string $candidateKey = self::CANDIDATE_KEY
    ) {
    }

    public function add(string $identifier, int $priority = 0): int
    {
        // 1. Add to the pool
        $this->redis->sAdd($this->setKey, $identifier);

        // 2. Check if this person is the "Chosen One" (The Candidate)
        // We do a GET to see who is currently selected.
        $currentCandidate = $this->redis->get($this->candidateKey);

        if ($currentCandidate === $identifier) {
            return 1;
        }

        // 3. Everyone else gets a generic high number.
        // We return the pool size, which is guaranteed to be >= 1.
        // If they are the ONLY person, sCard is 1, so they enter immediately.
        // If there are 10 people, sCard is 10, so they wait.
        return (int) $this->redis->sCard($this->setKey);
    }

    public function remove(string $identifier): void
    {
        $this->redis->sRem($this->setKey, $identifier);

        // If the winner enters (or leaves), we clear the candidate slot
        // so peek() can pick a new winner next time.
        $currentCandidate = $this->redis->get($this->candidateKey);
        if ($currentCandidate !== $identifier) {
            return;
        }

        $this->redis->del($this->candidateKey);
    }

    public function peek(): ?string
    {
        // 1. Is there already a chosen candidate waiting?
        $candidate = $this->redis->get($this->candidateKey);
        if ($candidate !== false && $this->redis->sIsMember($this->setKey, (string)$candidate)) {
            return (string) $candidate;
        }

        // 2. No candidate (or they left). Pick a NEW winner.
        $winner = $this->redis->sRandMember($this->setKey);

        if ($winner === false) {
            return null;
        }

        // 3. Persist the winner so add() knows to let them in.
        $this->redis->set($this->candidateKey, (string) $winner);

        return (string) $winner;
    }

    public function getPosition(string $identifier): ?int
    {
        if (!$this->redis->sIsMember($this->setKey, $identifier)) {
            return null;
        }

        $currentCandidate = $this->redis->get($this->candidateKey);

        if ($currentCandidate === $identifier) {
            return 1;
        }

        return (int) $this->redis->sCard($this->setKey);
    }
}
