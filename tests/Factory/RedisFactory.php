<?php
// phpcs:ignoreFile SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable

declare(strict_types=1);

namespace Clegginabox\Airlock\Tests\Factory;

use Redis;

class RedisFactory
{
    public static function create(): Redis
    {
        $url = $_ENV['REDIS_URL'] ?? throw new \RuntimeException('REDIS_URL not set in bootstrap');

        $parsed = parse_url($url);

        $redis = new Redis();
        $redis->connect(
            $parsed['host'] ?? '127.0.0.1',
            $parsed['port'] ?? 6379,
        );

        return $redis;
    }
}
