<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Bridge\Symfony\Seal;

use Clegginabox\Airlock\Seal\PortableToken;
use Symfony\Component\Lock\Key;

class SymfonyLockToken implements PortableToken
{
    public function __construct(private Key $key)
    {
    }

    public function getKey(): Key
    {
        return $this->key;
    }

    public function getResource(): string
    {
        return $this->key->__toString();
    }

    public function getId(): string
    {
        return hash('xxh128', serialize($this->key));
    }

    public function __toString(): string
    {
        return serialize($this->key);
    }
}
