<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Bridge\Amphp\Seal;

use Amp\Sync\Lock;
use Amp\Sync\Mutex;
use Clegginabox\Airlock\Exception\LeaseExpiredException;
use Clegginabox\Airlock\Seal\RefreshableSeal;
use Clegginabox\Airlock\Seal\ReleasableSeal;
use Clegginabox\Airlock\Seal\SealToken;

/**
 * Amp-backed Seal.
 *
 * IMPORTANT:
 * - Tokens are only valid in the *same PHP process* that acquired them.
 * - This is great for async workers (Amp/Revolt), not distributed web fleets.
 */
final class AmpMutexSeal implements ReleasableSeal, RefreshableSeal
{
    /** @var array<string, Lock> */
    private array $locks = [];

    /** @var array<string, float> */
    private array $acquiredAt = [];

    public function __construct(
        private readonly Mutex $mutex,
        private readonly string $resource = 'airlock',
        private readonly ?float $ttlInSeconds = null,
    ) {
    }

    public function tryAcquire(): ?SealToken
    {
        // Amp Mutex::acquire() waits cooperatively (doesn't block the whole process),
        // but it *does* wait. If you need a strict "try" you'd need cancellation/timeout.
        $lock = $this->mutex->acquire();

        $id = bin2hex(random_bytes(16));
        $this->locks[$id] = $lock;
        $this->acquiredAt[$id] = microtime(true);

        return new AmpMutexToken($this->resource, $id);
    }

    public function release(SealToken $token): void
    {
        if (!$token instanceof AmpMutexToken) {
            return;
        }

        $id = $token->getId();
        $lock = $this->locks[$id] ?? null;
        if (!$lock) {
            return;
        }

        unset($this->locks[$id], $this->acquiredAt[$id]);
        $lock->release();
    }

    public function refresh(SealToken $token, ?float $ttlInSeconds = null): SealToken
    {
        if (!$token instanceof AmpMutexToken) {
            throw new LeaseExpiredException((string) $token, 'Invalid token type');
        }

        // Amp locks don't have a lease in the Redis sense.
        // If you want a lease, enforce a "soft TTL" in your own bookkeeping.
        if ($this->isExpired($token)) {
            $this->release($token);
            throw new LeaseExpiredException((string) $token, 'Lease expired (soft TTL)');
        }

        return $token;
    }

    public function isExpired(AmpMutexToken $token): bool
    {
        $id = $token->getId();
        if (!isset($this->locks[$id])) {
            return true;
        }

        $ttl = $this->ttlInSeconds;
        if ($ttl === null) {
            return false;
        }

        return (microtime(true) - ($this->acquiredAt[$id] ?? 0.0)) >= $ttl;
    }

    public function isAcquired(AmpMutexToken $token): bool
    {
        return isset($this->locks[$token->getId()]) && !$this->isExpired($token);
    }

    public function getRemainingLifetime(AmpMutexToken $token): ?float
    {
        $id = $token->getId();
        if (!isset($this->locks[$id])) {
            return null;
        }

        if ($this->ttlInSeconds === null) {
            return null;
        }

        $elapsed = microtime(true) - ($this->acquiredAt[$id] ?? 0.0);
        return max(0.0, $this->ttlInSeconds - $elapsed);
    }

    public function __toString(): string
    {
        return $this->resource;
    }
}
