<?php

declare(strict_types=1);

namespace Clegginabox\Airlock;

use Clegginabox\Airlock\Seal\SealToken;

final readonly class ClaimResult
{
    private function __construct(
        private string $status,
        private ?SealToken $token,
        private string $topic,
    ) {
    }

    public static function admitted(SealToken $token, string $topic): self
    {
        return new self('admitted', $token, $topic);
    }

    public static function missed(string $topic): self
    {
        return new self('missed', null, $topic);
    }

    public static function unavailable(string $topic): self
    {
        return new self('unavailable', null, $topic);
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isAdmitted(): bool
    {
        return $this->status === 'admitted';
    }

    public function isMissed(): bool
    {
        return $this->status === 'missed';
    }

    public function isUnavailable(): bool
    {
        return $this->status === 'unavailable';
    }

    public function getToken(): ?SealToken
    {
        return $this->token;
    }

    public function getTopic(): string
    {
        return $this->topic;
    }
}
