<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Seal;

use Symfony\Component\Lock\LockFactory;

final class RemoteLockSeal implements RemoteSeal, ReleasableSeal, RefreshableSeal, PortableToken, RequiresTtl
{
    public function __construct(
        private LockFactory $factory,
        private string $resource = 'waiting-room',
        private float $ttlInSeconds = 300.0, // Better to enforce float than ?float
        private bool $autoRelease = false,
    ) {
    }

    public function refresh(string $token, ?float $ttlInSeconds = null): string
    {
        // TODO: Implement refresh() method.
    }

    public function release(string $token): void
    {
        // TODO: Implement release() method.
    }
}
