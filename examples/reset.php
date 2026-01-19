<?php

declare(strict_types=1);

/**
 * Test helper: clears all Redis keys related to a given example.
 * Only intended for e2e test isolation.
 */

header('Content-Type: application/json');

$example = $_GET['example'] ?? null;

if (!is_string($example)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing example parameter'], JSON_THROW_ON_ERROR);
    exit;
}

$redis = new Redis();
$redis->connect(getenv('REDIS_HOST') ?: '127.0.0.1', (int)(getenv('REDIS_PORT') ?: 6379));

$deleted = 0;

// Clear all keys containing this example identifier
$patterns = [
    "*{$example}*",
];

foreach ($patterns as $pattern) {
    $keys = $redis->keys($pattern);
    if ($keys) {
        $deleted += $redis->del(...$keys);
    }
}

echo json_encode([
    'ok'          => true,
    'example'     => $example,
    'keysDeleted' => $deleted,
], JSON_THROW_ON_ERROR);
