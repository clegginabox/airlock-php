<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Bridge\Symfony\Seal;

use Clegginabox\Airlock\Seal\RateLimitingSeal;
use Symfony\Component\RateLimiter\LimiterInterface;

class SymfonyRateLimiterSeal implements RateLimitingSeal
{
    public function __construct(
        private readonly LimiterInterface $limiter,
        private readonly string $resource = 'waiting-room',
    ) {
    }

    public function tryAcquire(): ?SymfonyRateLimiterToken
    {
        $result = $this->limiter->consume(1);

        if (!$result->isAccepted()) {
            return null;
        }

        return new SymfonyRateLimiterToken(
            $this->resource,
            bin2hex(random_bytes(16))
        );
    }
}
