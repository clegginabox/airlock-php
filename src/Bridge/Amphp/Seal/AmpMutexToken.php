<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Bridge\Amphp\Seal;

use Clegginabox\Airlock\Seal\SealToken;

/**
 * Local-only token for Amp mutex.
 *
 * NOT portable - only valid within the same PHP process.
 */
class AmpMutexToken implements SealToken
{
    public function __construct(
        private string $resource,
        private string $id,
    ) {}

    public function getResource(): string
    {
        return $this->resource;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function __toString(): string
    {
        return json_encode(['resource' => $this->resource, 'id' => $this->id]);
    }
}