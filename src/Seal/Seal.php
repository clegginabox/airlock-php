<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Seal;

interface Seal
{
    public function tryAcquire(): ?string;
}
