<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Queue\Storage\Lottery;

interface LotteryQueueStorage
{
    public function add(string $identifier, int $priority = 0): int;

    public function remove(string $identifier): void;

    public function peek(): ?string;

    public function getPosition(string $identifier): ?int;

    /**
     * @return list<string>
     */
    public function all(): array;
}
