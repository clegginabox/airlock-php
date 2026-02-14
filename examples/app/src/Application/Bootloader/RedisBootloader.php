<?php

declare(strict_types=1);

namespace App\Application\Bootloader;

use Redis;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\EnvironmentInterface;

final class RedisBootloader extends Bootloader
{
    protected const SINGLETONS = [
        Redis::class => [self::class, 'createRedis'],
    ];

    private function createRedis(EnvironmentInterface $env): Redis
    {
        $redis = new Redis();
        $redis->connect(
            $env->get('REDIS_HOST', '127.0.0.1'),
            (int) $env->get('REDIS_PORT', 6379),
        );

        return $redis;
    }
}
