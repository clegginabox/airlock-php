<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Bridge\Symfony\Seal;

use Clegginabox\Airlock\Exception\LeaseExpiredException;
use Clegginabox\Airlock\Seal\RefreshableSeal;
use Clegginabox\Airlock\Seal\ReleasableSeal;
use Clegginabox\Airlock\Seal\SealToken;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Exception\LockExpiredException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Throwable;

class SymfonyLockSeal implements ReleasableSeal, RefreshableSeal
{
    public function __construct(
        private LockFactory $factory,
        private string $resource = 'waiting-room',
        private float $ttlInSeconds = 300.0,
        private bool $autoRelease = false,
    ) {
    }

    public function tryAcquire(): ?SealToken
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

        return new SymfonyLockToken($key);
    }

    public function release(SealToken $token): void
    {
        if (!$token instanceof SymfonyLockToken) {
            return;
        }

        $lock = $this->factory->createLockFromKey($token->getKey());
        $lock->release();
    }

    public function refresh(SealToken $token, ?float $ttlInSeconds = null): SealToken
    {
        if (!$token instanceof SymfonyLockToken) {
            throw new LeaseExpiredException((string) $token, 'Invalid token type');
        }

        $lock = $this->factory->createLockFromKey(
            $token->getKey(),
            $ttlInSeconds ?? $this->ttlInSeconds
        );

        try {
            $effectiveTtl = $ttlInSeconds ?? $this->ttlInSeconds;
            $lock->refresh($effectiveTtl);

            return $token;
        } catch (LockExpiredException | LockConflictedException $e) {
            throw new LeaseExpiredException((string) $token, $e->getMessage());
        } catch (Throwable $e) {
            throw new LeaseExpiredException((string) $token, 'Unexpected error: ' . $e->getMessage());
        }
    }

    public function isExpired(SymfonyLockToken $token): bool
    {
        return $this->resolveLock($token)?->isExpired() ?? true;
    }

    public function isAcquired(SymfonyLockToken $token): bool
    {
        return $this->resolveLock($token)?->isAcquired() ?? false;
    }

    public function getRemainingLifetime(SymfonyLockToken $token): ?float
    {
        return $this->resolveLock($token)?->getRemainingLifetime();
    }

    private function resolveLock(SymfonyLockToken $token): ?LockInterface
    {
        return $this->factory->createLockFromKey(
            $token->getKey(),
            $this->ttlInSeconds,
            $this->autoRelease
        );
    }

    public function __toString(): string
    {
        return $this->resource;
    }
}
