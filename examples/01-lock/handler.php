<?php

/**
 * Example 01: Double-click prevention / single-flight.
 *
 * The lock is acquired in start.php (HTTP layer) for instant feedback.
 * This handler receives the serialized key, does the work, and releases the lock.
 */

declare(strict_types=1);

use Clegginabox\Airlock\Bridge\Symfony\Seal\SymfonyLockSeal;
use Clegginabox\Airlock\Bridge\Symfony\Seal\SymfonyLockToken;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\RedisStore;

return static function (Redis $redis, array $job, callable $setStatus): void {
    $example  = '01-lock';
    $clientId = (string)($job['clientId'] ?? 'anonymous');
    $duration = (int)($job['durationSeconds'] ?? 5);
    $serializedKey = $job['serializedKey'] ?? null;

    if (!$serializedKey) {
        $setStatus($example, $clientId, [
            'state' => 'error',
            'message' => 'Missing serialized key in job',
            'ts' => time(),
        ]);
        return;
    }

    // Reconstruct the seal and token from the serialized key
    $seal = new SymfonyLockSeal(
        factory: new LockFactory(new RedisStore($redis)),
        resource: 'examples:01-lock:single-flight',
        ttlInSeconds: max(10, $duration + 10),
        autoRelease: false,
    );

    $key = unserialize($serializedKey, ['allowed_classes' => [Key::class]]);
    $token = new SymfonyLockToken($key);

    try {
        $setStatus($example, $clientId, [
            'state' => 'running',
            'message' => "Processing... (holding lock for {$duration}s)",
            'ts' => time(),
        ]);

        sleep($duration);

        $setStatus($example, $clientId, [
            'state' => 'done',
            'message' => 'Done',
            'ts' => time(),
        ]);
    } finally {
        $seal->release($token);
    }
};
