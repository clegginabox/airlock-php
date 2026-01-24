<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Bridge\Symfony\Seal;

use Clegginabox\Airlock\Seal\SealToken;

class SymfonyRateLimiterToken implements SealToken
{
    public function __construct(
        private string $resource,
        private string $id,
    ) {
    }

    public function getResource(): string
    {
        return $this->resource;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function __toString(): string
    {
        return $this->resource . ':' . $this->id;
    }
}
