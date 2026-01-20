<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Tests\Unit\Bridge\Symfony\Seal;

use Clegginabox\Airlock\Bridge\Symfony\Seal\SymfonySemaphoreToken;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Semaphore\Key;

class SymfonySemaphoreTokenTest extends TestCase
{
    public function testGetKey(): void
    {
        $key = new Key('test-resource', 1, 1);
        $token = new SymfonySemaphoreToken($key);

        $this->assertSame($key, $token->getKey());
    }

    public function testGetResource(): void
    {
        $key = new Key('test-resource', 1, 1);
        $token = new SymfonySemaphoreToken($key);

        $this->assertSame('test-resource', $token->getResource());
    }

    public function testGetId(): void
    {
        $key = new Key('test-resource', 1, 1);
        $token = new SymfonySemaphoreToken($key);

        $this->assertSame(hash('xxh128', serialize($key)), $token->getId());
    }

    public function testToString(): void
    {
        $key = new Key('test-resource', 1, 1);
        $token = new SymfonySemaphoreToken($key);

        $this->assertSame(serialize($key), $token->__toString());
    }
}
