<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Seal;

use Clegginabox\Airlock\Exception\LeaseExpiredException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Exception\LockExpiredException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\LockFactory;
use Throwable;

class LocalLockSeal implements LocalSeal, ReleasableSeal, RefreshableSeal
{
    public function __construct(
        private LockFactory $factory,
        private string $resource = 'waiting-room',
        private float $ttlInSeconds = 300.0, // Better to enforce float than ?float
        private bool $autoRelease = false,
    ) {
    }

    public function tryAcquire(): ?string
    {
        $key = new Key($this->resource);

        $lock = $this->factory->createLockFromKey(
            $key,
            $this->ttlInSeconds,
            $this->autoRelease,
        );

        if (!$lock->acquire()) {
            return null;
        }

        return serialize($key);
    }

    public function release(string $token): void
    {
        $key = unserialize($token, ['allowed_classes' => [Key::class]]);

        if (!$key instanceof Key) {
            return;
        }

        // We don't care about the TTL during release
        $lock = $this->factory->createLockFromKey($key);
        $lock->release();
    }

    public function refresh(string $token, ?float $ttlInSeconds = null): string
    {
        $key = unserialize($token, ['allowed_classes' => [Key::class]]);

        if (!$key instanceof Key) {
            throw new LeaseExpiredException($token, 'Invalid token structure');
        }

        $lock = $this->factory->createLockFromKey(
            $key,
            $ttlInSeconds ?? $this->ttlInSeconds
        );

        try {
            $effectiveTtl = $ttlInSeconds ?? $this->ttlInSeconds;
            $lock->refresh($effectiveTtl);

            return serialize($key);
        } catch (LockExpiredException | LockConflictedException $e) {
            throw new LeaseExpiredException($token, $e->getMessage());
        } catch (Throwable $e) {
            throw new LeaseExpiredException($token, 'Unexpected error: ' . $e->getMessage());
        }
    }
}
