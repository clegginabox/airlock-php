<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Factory;

use Clegginabox\Airlock\Notifier\AirlockNotifierInterface;
use Clegginabox\Airlock\Queue\QueueInterface;
use InvalidArgumentException;

/**
 * Input DTO for SymfonyAirlockFactory.
 *
 * This keeps the factory method signature compact and makes the decision
 * points explicit:
 * - queue present => queue-based airlock
 * - queue absent  => opportunistic airlock
 * - limit=1       => lock-based seal
 * - limit>1       => semaphore-based seal (if backend supports it)
 */
final readonly class SymfonyAirlockFactoryOptions
{
    public function __construct(
        public object|string $storeConnection,
        public int $limit = 1,
        public string $resource = 'waiting-room',
        public ?float $ttlInSeconds = 300.0,
        public bool $autoRelease = false,
        public ?QueueInterface $queue = null,
        public ?AirlockNotifierInterface $notifier = null,
        public string $topicPrefix = '/waiting-room',
    ) {
        if ($this->limit < 1) {
            throw new InvalidArgumentException('Limit must be >= 1.');
        }
    }

    public function usesQueue(): bool
    {
        return $this->queue !== null;
    }
}
