<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Queue\Storage\Fifo;

class InMemoryFifoQueueStore implements FifoQueueStorage
{
    /**
     * @var list<string>
     */
    private array $queue = [];

    public function addToBack(string $identifier): int
    {
        if (!in_array($identifier, $this->queue, true)) {
            $this->queue[] = $identifier;
        }

        return $this->getPosition($identifier) ?? -1;
    }

    public function remove(string $identifier): void
    {
        $this->queue = array_values(
            array_filter($this->queue, static fn ($id) => $id !== $identifier)
        );
    }

    public function peekFront(): ?string
    {
        return $this->queue[0] ?? null;
    }

    public function popFront(): ?string
    {
        return array_shift($this->queue);
    }

    public function getPosition(string $identifier): ?int
    {
        $index = array_search($identifier, $this->queue, true);

        return $index === false ? null : $index + 1;
    }

    public function contains(string $identifier): bool
    {
        return in_array($identifier, $this->queue, true);
    }

    public function clear(): void
    {
        $this->queue = [];
    }

    public function all(): array
    {
        return $this->queue;
    }
}
