<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Queue;

use Clegginabox\Airlock\HealthCheckerInterface;

class BackpressureQueue implements QueueInterface
{
    public function __construct(
        private QueueInterface $inner,
        private HealthCheckerInterface $healthChecker,
        private float $minHealthToAdmit = 0.2,
    ) {
    }

    public function add(string $identifier, int $priority = 0): int
    {
        return $this->inner->add($identifier, $priority);
    }

    public function remove(string $identifier): void
    {
        $this->inner->remove($identifier);
    }

    public function peek(): ?string
    {
        $score = $this->healthChecker->getScore();

        if ($score < $this->minHealthToAdmit) {
            return null;
        }

        return $this->inner->peek();
    }

    public function getPosition(string $identifier): ?int
    {
        return $this->inner->getPosition($identifier);
    }
}
