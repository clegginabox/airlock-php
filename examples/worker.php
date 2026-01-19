<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$redis = new Redis();
$redis->connect(getenv('REDIS_HOST') ?: '127.0.0.1', (int)(getenv('REDIS_PORT') ?: 6379));

$queueKey = 'airlock:examples:jobs';

echo "Airlock examples worker running. Queue: {$queueKey}\n";

$handlers = [
    '01-lock' => require __DIR__ . '/01-lock/handler.php',
    // '02-semaphore' => require ...
];

while (true) {
    $item = $redis->brPop(['airlock:examples:jobs'], 5);
    if (!$item) {
        echo ".";
        continue;
    }

    $job = json_decode($item[1], true, 512, JSON_THROW_ON_ERROR);
    $example = $job['example'] ?? null;
    echo "\n[worker] Received job: example={$example}, clientId={$job['clientId']}\n";

    if (!isset($handlers[$example])) {
        echo "[worker] No handler for example: {$example}\n";
        continue;
    }

    echo "[worker] Processing with handler...\n";
    $handlers[$example]($redis, $job, function (
        string $example,
        string $clientId,
        array $status
    ) use ($redis) {
        $key = "airlock:examples:$example:$clientId:status";
        $redis->setex($key, 300, json_encode($status, JSON_THROW_ON_ERROR));
    });
}

function statusKey(string $example, string $clientId): string
{
    return "airlock:examples:{$example}:{$clientId}:status";
}

function setStatus(Redis $redis, string $example, string $clientId, array $status, int $ttlSeconds = 300): void
{
    $key = statusKey($example, $clientId);
    $redis->set($key, json_encode($status, JSON_THROW_ON_ERROR));
    $redis->expire($key, $ttlSeconds);
}
