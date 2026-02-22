<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Supervisor;

/**
 * Immutable result of a single supervisor tick
 */
final readonly class SupervisorTickResult
{
    /**
     * @param list<string> $evicted Identifiers removed from the queue this tick
     * @param string|null $notified Identifier that was notified, or null if none
     */
    public function __construct(
        public array $evicted,
        public ?string $notified,
    ) {
    }

    public function hadActivity(): bool
    {
        return $this->evicted !== [] || $this->notified !== null;
    }
}
