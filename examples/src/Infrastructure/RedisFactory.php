<?php

declare(strict_types=1);

namespace App\Infrastructure;

use Redis;

final class RedisFactory
{
    public function create(): Redis
    {
        $redis = new Redis();

        $host = getenv('REDIS_HOST') ?: '127.0.0.1';
        $port = (int) (getenv('REDIS_PORT') ?: 6379);

        $redis->connect($host, $port);

        return $redis;
    }
}
