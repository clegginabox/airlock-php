<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Seal;

use Clegginabox\Airlock\Exception\LeaseExpiredException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Exception\LockExpiredException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Throwable;

final readonly class LockSeal implements SealInterface
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

    public function refresh(string $token, ?float $ttlInSeconds = null): ?string
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

    public function isExpired(string $token): bool
    {
        return $this->resolveLock($token)?->isExpired() ?? true;
    }

    public function isAcquired(string $token): bool
    {
        return $this->resolveLock($token)?->isAcquired() ?? false;
    }

    public function getRemainingLifetime(string $token): ?float
    {
        return $this->resolveLock($token)?->getRemainingLifetime();
    }

    private function resolveLock(string $token): ?LockInterface
    {
        $key = unserialize($token, ['allowed_classes' => [Key::class]]);

        if (!$key instanceof Key) {
            return null;
        }

        return $this->factory->createLockFromKey(
            $key,
            $this->ttlInSeconds,
            $this->autoRelease
        );
    }

    public function __toString(): string
    {
        return $this->resource;
    }
}
