<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Tests\Unit\Bridge\Symfony\Seal;

use Clegginabox\Airlock\Bridge\Symfony\Seal\SymfonyLockToken;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\Key;

class SymfonyLockTokenTest extends TestCase
{
    public function testGetKey(): void
    {
        $key = new Key('test-resource');
        $token = new SymfonyLockToken($key);

        $this->assertSame($key, $token->getKey());
    }

    public function testGetResource(): void
    {
        $key = new Key('test-resource');
        $token = new SymfonyLockToken($key);

        $this->assertSame($key->__toString(), $token->getResource());
    }

    public function testGetId(): void
    {
        $key = new Key('test-resource');
        $token = new SymfonyLockToken($key);

        $this->assertSame(
            hash('xxh128', serialize($key)),
            $token->getId()
        );
    }

    public function testToString(): void
    {
        $key = new Key('test-resource');
        $token = new SymfonyLockToken($key);

        $this->assertSame($token->__toString(), serialize($key));
    }
}
