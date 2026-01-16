<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Queue\Storage\Fifo\Redis;

use Clegginabox\Airlock\Queue\Storage\Fifo\FifoQueueStorage;
use Redis;

/**
 * Redis implementation of queue storage using LIST + SET for O(1) membership checks.
 *
 * Uses Lua scripts for atomic operations to prevent race conditions.
 * The SET tracks membership for fast contains() checks, while the LIST
 * maintains FIFO ordering.
 */
final readonly class RedisFifoQueueStore implements FifoQueueStorage
{
    private const string DEFAULT_LIST_KEY = 'fifo:queue:list';
    private const string DEFAULT_SET_KEY = 'fifo:queue:set';

    public function __construct(
        private Redis $redis,
        private string $listKey = self::DEFAULT_LIST_KEY,
        private string $setKey = self::DEFAULT_SET_KEY,
    ) {
    }

    public function addToBack(string $identifier): int
    {
        // Lua Script:
        // 1. Check if user is already in the SET (sismember).
        // 2. If 0 (not in set), push to LIST (rpush) and add to SET (sadd).
        // 3. Return the position (LLEN).
        // 4. If 1 (already in set), find position (lpos).
        // 5. [HEALING] If pos is nil (corruption), re-add to LIST and return new position.
        $script = <<<'LUA'
            local setKey = KEYS[1]
            local listKey = KEYS[2]
            local id = ARGV[1]

            if redis.call('SISMEMBER', setKey, id) == 0 then
                -- Normal Case: New User
                redis.call('SADD', setKey, id)
                redis.call('RPUSH', listKey, id)
                return redis.call('LLEN', listKey)
            else
                -- Existing User (or Zombie)
                local pos = redis.call('LPOS', listKey, id)

                if pos then
                    return pos + 1 -- Normal Case: User found
                else
                    -- HEALING LOGIC: User in Set but missing from List.
                    -- We re-add them to the back of the line to fix the state.
                    redis.call('RPUSH', listKey, id)
                    return redis.call('LLEN', listKey)
                end
            end
        LUA;

        $result = $this->redis->eval($script, [$this->setKey, $this->listKey, $identifier], 2);

        return (int) $result;
    }

    public function remove(string $identifier): void
    {
        $this->redis->pipeline()
            ->sRem($this->setKey, $identifier)
            ->lRem($this->listKey, $identifier)
            ->exec();
    }

    public function peekFront(): ?string
    {
        $value = $this->redis->lIndex($this->listKey, 0);

        return $value === false ? null : (string) $value;
    }

    public function popFront(): ?string
    {
        // Lua Script:
        // 1. Pop left from LIST (lpop).
        // 2. If result, remove from SET (srem).
        // 3. Return result.
        $script = <<<'LUA'
            local listKey = KEYS[1]
            local setKey = KEYS[2]

            local id = redis.call('LPOP', listKey)
            if id then
                redis.call('SREM', setKey, id)
                return id
            else
                return nil
            end
        LUA;

        $result = $this->redis->eval($script, [$this->listKey, $this->setKey], 2);

        return $result === false ? null : (string) $result;
    }

    public function getPosition(string $identifier): ?int
    {
        // Check SET first to avoid O(N) scan if they aren't even here
        if (!$this->redis->sIsMember($this->setKey, $identifier)) {
            return null;
        }

        $rank = $this->redis->lPos($this->listKey, $identifier, []);

        if ($rank === false) {
            return null;
        }

        return $rank + 1;
    }

    public function contains(string $identifier): bool
    {
        return (bool) $this->redis->sIsMember($this->setKey, $identifier);
    }
}
