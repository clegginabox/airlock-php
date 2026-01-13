<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Bridge\Amphp\Seal;

use Amp\Sync\Mutex;
use Clegginabox\Airlock\Exception\LeaseExpiredException;
use Clegginabox\Airlock\Seal\SealInterface;
use Symfony\Component\Lock\Lock;

/**
 * Amp-backed Seal.
 *
 * IMPORTANT:
 * - Tokens are only valid in the *same PHP process* that acquired them.
 * - This is great for async workers (Amp/Revolt), not distributed web fleets.
 */
final class AmpMutexSeal implements SealInterface
{
    /** @var array<string, Lock> */
    private array $locks = [];

    /** @var array<string, float> */
    private array $acquiredAt = [];

    public function __construct(
        private readonly Mutex $mutex,
        private readonly string $resource = 'airlock',
        private readonly ?float $ttlInSeconds = null, // optional "soft TTL" you enforce yourself
    ) {}

    public function tryAcquire(): ?string
    {
        // Amp Mutex::acquire() waits cooperatively (doesn't block the whole process),
        // but it *does* wait. If you need a strict "try" you’d need cancellation/timeout.
        //
        // In a worker, that’s often acceptable: callers *want* to wait for the lock.
        $lock = $this->mutex->acquire();

        $token = bin2hex(random_bytes(16));
        $this->locks[$token] = $lock;
        $this->acquiredAt[$token] = microtime(true);

        return $token;
    }

    public function release(string $token): void
    {
        $lock = $this->locks[$token] ?? null;
        if (!$lock) {
            return;
        }

        unset($this->locks[$token], $this->acquiredAt[$token]);
        $lock->release();
    }

    public function refresh(string $token, ?float $ttlInSeconds = null): string
    {
        // Amp locks don’t have a lease in the Redis sense.
        // If you want a lease, enforce a "soft TTL" in your own bookkeeping.
        if ($this->isExpired($token)) {
            $this->release($token);
            throw new LeaseExpiredException($token, 'Lease expired (soft TTL)');
        }

        return $token;
    }

    public function isExpired(string $token): bool
    {
        if (!isset($this->locks[$token])) {
            return true;
        }

        $ttl = $this->ttlInSeconds;
        if ($ttl === null) {
            return false;
        }

        return (microtime(true) - ($this->acquiredAt[$token] ?? 0.0)) >= $ttl;
    }

    public function isAcquired(string $token): bool
    {
        return isset($this->locks[$token]) && !$this->isExpired($token);
    }

    public function getRemainingLifetime(string $token): ?float
    {
        if (!isset($this->locks[$token])) {
            return null;
        }

        if ($this->ttlInSeconds === null) {
            return null;
        }

        $elapsed = microtime(true) - ($this->acquiredAt[$token] ?? 0.0);
        return max(0.0, $this->ttlInSeconds - $elapsed);
    }

    public function __toString(): string
    {
        return $this->resource;
    }
}
