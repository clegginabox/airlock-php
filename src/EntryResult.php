<?php

declare(strict_types=1);

namespace Clegginabox\Airlock;

final readonly class EntryResult
{
    private function __construct(
        private bool $admitted,
        private ?string $token,
        private ?int $position,
        private string $topic,
    ) {
    }

    public static function admitted(string $token, string $topic): self
    {
        return new self(true, $token, null, $topic);
    }

    public static function queued(int $position, string $topic): self
    {
        return new self(false, null, $position, $topic);
    }

    public function isAdmitted(): bool
    {
        return $this->admitted;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function getTopic(): string
    {
        return $this->topic;
    }
}
