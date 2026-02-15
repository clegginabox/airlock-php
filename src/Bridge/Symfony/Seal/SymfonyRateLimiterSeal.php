<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Bridge\Symfony\Seal;

use Clegginabox\Airlock\Seal\RateLimitingSeal;
use Symfony\Component\RateLimiter\LimiterInterface;

final readonly class SymfonyRateLimiterSeal implements RateLimitingSeal
{
    public function __construct(
        private LimiterInterface $limiter,
        private string $resource = 'waiting-room',
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
