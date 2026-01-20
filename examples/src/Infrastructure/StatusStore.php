<?php

declare(strict_types=1);

namespace App\Infrastructure;

use Redis;

final class StatusStore
{
    private string $prefix;

    private int $ttlSeconds;

    public function __construct(
        private readonly Redis $redis,
        ?string $prefix = null,
        ?int $ttlSeconds = null
    ) {
        $this->prefix = $prefix ?? 'airlock:examples';
        $this->ttlSeconds = $ttlSeconds ?? 300;
    }

    public function get(string $example, string $clientId): ?array
    {
        $data = $this->redis->get($this->key($example, $clientId));

        if ($data === false) {
            return null;
        }

        return json_decode($data, true, 512, JSON_THROW_ON_ERROR);
    }

    public function set(string $example, string $clientId, array $status): void
    {
        $this->redis->setex(
            $this->key($example, $clientId),
            $this->ttlSeconds,
            json_encode($status, JSON_THROW_ON_ERROR)
        );
    }

    private function key(string $example, string $clientId): string
    {
        return "{$this->prefix}:{$example}:{$clientId}:status";
    }
}
