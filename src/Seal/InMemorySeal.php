<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Seal;

class InMemorySeal implements ReleasableSeal, RefreshableSeal
{
    public function tryAcquire(): ?SealToken
    {
    }

    public function refresh(SealToken $token, ?float $ttlInSeconds = null): SealToken
    {
        // TODO: Implement refresh() method.
    }

    public function release(SealToken $token): void
    {
        // TODO: Implement release() method.
    }
}
