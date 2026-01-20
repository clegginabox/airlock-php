<?php

// phpcs:ignoreFile Generic.Files.SideEffects
// phpcs:ignoreFile SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable

declare(strict_types=1);

use Testcontainers\Modules\RedisContainer;
use Testcontainers\Container\StartedGenericContainer;

require dirname(__DIR__) . '/vendor/autoload.php';

$redis = createRedisContainer();

register_shutdown_function(static function () use ($redis): void {
    $redis->stop();
});

function createRedisContainer(): StartedGenericContainer
{
    $redis = new RedisContainer('8.4-alpine')->start();
    $host = $redis->getHost();

    if (!runningInDocker()) {
        $host = match ($host) {
            'localhost', '0.0.0.0' => '127.0.0.1',
            default => $host,
        };
    } else {
        $host = dockerHostAddress();
    }

    $_ENV['REDIS_URL'] = sprintf("redis://%s:%s", $host, $redis->getFirstMappedPort());

    return $redis;
}

/**
 * Best-effort check for running in a Docker container.
 */
function runningInDocker(): bool
{
    if (is_file('/.dockerenv')) {
        return true;
    }

    $cgroup = @file_get_contents('/proc/1/cgroup') ?: '';
    return (bool) preg_match('/docker|containerd|kubepods/i', $cgroup);
}

/**
 * Resolve the Docker host from inside a container.
 * Prefers host.docker.internal; falls back to default gateway (bridge).
 */
function dockerHostAddress(): string
{
    // Try special DNS name
    $ip = gethostbyname('host.docker.internal');
    if ($ip !== 'host.docker.internal') {
        return $ip;
    }

    // Fallback: parse /proc/net/route for default gateway (Linux)
    $lines = @file('/proc/net/route') ?: [];
    foreach ($lines as $line) {
        $cols = preg_split('/\s+/', trim($line));
        // If Destination is 00000000, it's the default route
        if (isset($cols[1], $cols[2]) && $cols[1] === '00000000') {
            $hex = $cols[2]; // little-endian hex
            $bytes = array_map('hexdec', array_reverse(str_split($hex, 2)));
            return implode('.', $bytes); // e.g. 172.17.0.1
        }
    }

    // Worst case, common default bridge gateway:
    return '172.17.0.1';
}
