<?php

declare(strict_types=1);

namespace App\Infrastructure;

use Redis;

final class JobQueue
{
    private string $queueKey;

    public function __construct(
        private readonly Redis $redis,
        ?string $queueKey = null
    ) {
        $this->queueKey = $queueKey ?? 'airlock:examples:jobs';
    }

    public function enqueue(string $example, array $payload): void
    {
        $job = array_merge([
            'example' => $example,
            'ts' => time(),
        ], $payload);

        $this->redis->lPush($this->queueKey, json_encode($job, JSON_THROW_ON_ERROR));
    }
}
