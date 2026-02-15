<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Seal;

class CompositeSeal implements Seal, ReleasableSeal, RefreshableSeal
{
    public function __construct(
        private LockingSeal&ReleasableSeal&RefreshableSeal $lockingSeal,
        private RateLimitingSeal $rateLimitingSeal
    ) {
    }

    public function tryAcquire(): ?SealToken
    {
        $lockToken = $this->lockingSeal->tryAcquire();

        if ($lockToken === null) {
            return null;
        }

        $limiter = $this->rateLimitingSeal->tryAcquire();

        if ($limiter === null) {
            $this->lockingSeal->release($lockToken);

            return null;
        }

        return $lockToken;
    }

    public function release(SealToken $token): void
    {
        $this->lockingSeal->release($token);
    }

    public function refresh(SealToken $token, ?float $ttlInSeconds = null): SealToken
    {
        return $this->lockingSeal->refresh($token, $ttlInSeconds);
    }
}
