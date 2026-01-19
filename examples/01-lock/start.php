<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Clegginabox\Airlock\OpportunisticAirlock;
use Clegginabox\Airlock\Bridge\Symfony\Seal\SymfonyLockSeal;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\RedisStore;

header('Content-Type: application/json');

$redis = new Redis();
$redis->connect(getenv('REDIS_HOST') ?: '127.0.0.1', (int)(getenv('REDIS_PORT') ?: 6379));

$cookieName = 'airlock_demo_id';
$clientId = $_COOKIE[$cookieName] ?? null;

if (!is_string($clientId) || strlen($clientId) < 8) {
    $clientId = bin2hex(random_bytes(8));
    setcookie($cookieName, $clientId, [
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
}

$duration = 5;

// Use Airlock to acquire the lock immediately
$seal = new SymfonyLockSeal(
    factory: new LockFactory(new RedisStore($redis)),
    resource: 'examples:01-lock:single-flight',
    ttlInSeconds: max(10, $duration + 10),
    autoRelease: false,
);

$airlock = new OpportunisticAirlock($seal);
$result = $airlock->enter($clientId);

if (!$result->isAdmitted()) {
    http_response_code(409);
    echo json_encode([
        'ok' => false,
        'error' => 'Already processing (server-side lock held)',
        'clientId' => $clientId,
    ], JSON_THROW_ON_ERROR);
    exit;
}

// Serialize the token's key for the worker to release later
$token = $result->getToken();
$serializedKey = (string) $token;

$job = [
    'example' => '01-lock',
    'clientId' => $clientId,
    'durationSeconds' => $duration,
    'serializedKey' => $serializedKey,
    'ts' => time(),
];

$redis->lPush('airlock:examples:jobs', json_encode($job, JSON_THROW_ON_ERROR));

http_response_code(202);
echo json_encode(['ok' => true, 'clientId' => $clientId], JSON_THROW_ON_ERROR);