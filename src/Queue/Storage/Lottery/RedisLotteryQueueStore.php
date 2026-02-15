<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Queue\Storage\Lottery;

use Redis;

class RedisLotteryQueueStore implements LotteryQueueStorage
{
    private const string SET_KEY = 'airlock:pool';

    private const string CANDIDATE_KEY = 'airlock:pool:candidate';

    public function __construct(
        private readonly Redis $redis,
        private readonly string $setKey = self::SET_KEY,
        private readonly string $candidateKey = self::CANDIDATE_KEY,
        private readonly int $candidateTtlSeconds = 20,
    ) {
    }

    public function add(string $identifier, int $priority = 0): int
    {
        $this->redis->sAdd($this->setKey, $identifier);

        $currentCandidate = $this->redis->get($this->candidateKey);

        if ($currentCandidate === $identifier) {
            return 1;
        }

        return (int) $this->redis->sCard($this->setKey);
    }

    public function remove(string $identifier): void
    {
        $this->redis->sRem($this->setKey, $identifier);

        $currentCandidate = $this->redis->get($this->candidateKey);
        if ($currentCandidate !== $identifier) {
            return;
        }

        $this->redis->del($this->candidateKey);
    }

    public function peek(): ?string
    {
        $candidate = $this->redis->get($this->candidateKey);
        if ($candidate !== false) {
            if ($this->redis->sIsMember($this->setKey, (string) $candidate)) {
                return (string) $candidate;
            }

            $this->redis->del($this->candidateKey);
        }

        $winner = $this->redis->sRandMember($this->setKey);

        if ($winner === false) {
            return null;
        }

        $this->redis->set($this->candidateKey, (string) $winner, ['nx', 'ex' => $this->candidateTtlSeconds]);

        $candidate = $this->redis->get($this->candidateKey);

        return $candidate === false ? null : (string) $candidate;
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
