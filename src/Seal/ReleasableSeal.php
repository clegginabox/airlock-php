<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Seal;

/**
 * The acquired permit can be explicitly released.
 */
interface ReleasableSeal
{
    public function release(string $token): void;
}
