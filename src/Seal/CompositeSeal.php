<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Seal;

final readonly class CompositeSeal implements Seal, ReleasableSeal
{
    public function __construct(
        private Seal&ReleasableSeal $lockingSeal,
        private Seal $rateLimitingSeal
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
}
