<?php

// phpcs:ignoreFile SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable

declare(strict_types=1);

header('Content-Type: application/json');

$example = $_GET['example'] ?? null;
$clientId = $_GET['clientId'] ?? $_COOKIE['airlock_demo_id'] ?? null;

if (!is_string($example) || !is_string($clientId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing example or clientId'], JSON_THROW_ON_ERROR);
    exit;
}

$redis = new Redis();
$redis->connect(getenv('REDIS_HOST') ?: '127.0.0.1', (int)(getenv('REDIS_PORT') ?: 6379));

$key = "airlock:examples:{$example}:{$clientId}:status";
$data = $redis->get($key);

if ($data === false) {
    echo json_encode(['state' => 'pending', 'message' => 'Waiting for worker...'], JSON_THROW_ON_ERROR);
    exit;
}

echo $data;
