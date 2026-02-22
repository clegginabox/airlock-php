<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Reservation;

use Redis;

final readonly class RedisReservationStore implements ReservationStoreInterface
{
    private const string DEFAULT_KEY_PREFIX = 'airlock:reservation';

    public function __construct(
        private Redis $redis,
        private string $keyPrefix = self::DEFAULT_KEY_PREFIX,
    ) {
    }

    public function reserve(string $identifier, int $ttlSeconds): string
    {
        $nonce = bin2hex(random_bytes(16));
        $ttl = max(1, $ttlSeconds);

        $this->redis->set($this->keyFor($identifier), $nonce, ['ex' => $ttl]);

        return $nonce;
    }

    public function isReservedFor(string $identifier, string $nonce): bool
    {
        $value = $this->redis->get($this->keyFor($identifier));

        if ($value === false) {
            return false;
        }

        return hash_equals((string) $value, $nonce);
    }

    public function consume(string $identifier, string $nonce): bool
    {
        $script = <<<'LUA'
            local key = KEYS[1]
            local expected = ARGV[1]

            local current = redis.call('GET', key)
            if not current then
                return 0
            end

            if current ~= expected then
                return 0
            end

            redis.call('DEL', key)
            return 1
        LUA;

        $result = $this->redis->eval($script, [$this->keyFor($identifier), $nonce], 1);

        return (int) $result === 1;
    }

    public function getReservationNonce(string $identifier): ?string
    {
        $value = $this->redis->get($this->keyFor($identifier));

        if ($value === false) {
            return null;
        }

        return (string) $value;
    }

    public function clear(string $identifier): void
    {
        $this->redis->del($this->keyFor($identifier));
    }

    private function keyFor(string $identifier): string
    {
        return rtrim($this->keyPrefix, ':') . ':' . rawurlencode($identifier);
    }
}
