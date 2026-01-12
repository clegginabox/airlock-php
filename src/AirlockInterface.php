<?php

declare(strict_types=1);

namespace Clegginabox\Airlock;

interface AirlockInterface
{
    public function enter(string $identifier): EntryResult;

    public function leave(string $identifier): void;

    public function release(string $token): void; // frees slot + notifies next

    public function refresh(string $token, ?float $ttlInSeconds = null): ?string;

    public function getPosition(string $identifier): ?int;

    public function getTopic(string $identifier): string;
}
