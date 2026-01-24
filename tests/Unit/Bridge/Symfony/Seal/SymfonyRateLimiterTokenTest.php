<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Tests\Unit\Bridge\Symfony\Seal;

use Clegginabox\Airlock\Bridge\Symfony\Seal\SymfonyRateLimiterToken;
use PHPUnit\Framework\TestCase;

class SymfonyRateLimiterTokenTest extends TestCase
{
    public function testGetResource(): void
    {
        $token = new SymfonyRateLimiterToken('test-resource', 'id');
        $this->assertEquals('test-resource', $token->getResource());
    }

    public function testGetId(): void
    {
        $token = new SymfonyRateLimiterToken('test-resource', 'id');
        $this->assertEquals('id', $token->getId());
    }

    public function testToString(): void
    {
        $token = new SymfonyRateLimiterToken('test-resource', 'id');

        $this->assertEquals(
            'test-resource:id',
            $token->__toString()
        );
    }
}
