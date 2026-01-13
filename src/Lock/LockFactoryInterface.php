<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Lock;

use Symfony\Component\Lock\Key;

interface LockFactoryInterface
{
    public function createLock(string $resource, ?float $ttl = 300.0, bool $autoRelease = true);

    public function createLockFromKey(Key $key, ?float $ttl = 300.0, bool $autoRelease = true);
}
