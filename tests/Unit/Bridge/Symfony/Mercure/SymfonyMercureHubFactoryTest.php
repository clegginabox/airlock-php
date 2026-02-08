<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Tests\Unit\Bridge\Symfony\Mercure;

use Clegginabox\Airlock\Airlock;
use Clegginabox\Airlock\Bridge\Symfony\Mercure\SymfonyMercureHubFactory;
use JsonException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mercure\Hub;

class SymfonyMercureHubFactoryTest extends TestCase
{
    private const string HUB_URL = 'https://example.com/hub';

    public function testCreateReturnsHub(): void
    {
        $hub = SymfonyMercureHubFactory::create(self::HUB_URL, random_bytes(32));

        $this->assertSame(self::HUB_URL, $hub->getPublicUrl());
    }

    public function testCreateForAirlockScopesJwtToTopic(): void
    {
        $airlock = $this->createMock(Airlock::class);
        $airlock->expects($this->once())
            ->method('getTopic')
            ->with('user-123')
            ->willReturn('queue/user-123');

        $hub = SymfonyMercureHubFactory::createForAirlock(
            self::HUB_URL,
            random_bytes(32),
            $airlock,
            'user-123'
        );

        $this->assertInstanceOf(Hub::class, $hub);

        $payload = $this->decodeJwtPayload($hub->getProvider()->getJwt());

        $this->assertSame(['queue/user-123'], $payload['mercure']['subscribe'] ?? null);
        $this->assertSame(['queue/user-123'], $payload['mercure']['publish'] ?? null);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    private function decodeJwtPayload(string $jwt): array
    {
        $parts = explode('.', $jwt);
        $this->assertCount(3, $parts, 'Expected a JWT with 3 parts.');

        $payload = $this->base64UrlDecode($parts[1]);

        return json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder !== 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'), true);

        $this->assertNotFalse($decoded, 'JWT payload was not valid base64url.');

        return $decoded;
    }
}
