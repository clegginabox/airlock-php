<?php

declare(strict_types=1);

namespace App\RedisLotteryQueue;

use Clegginabox\Airlock\Bridge\Symfony\Seal\SymfonySemaphoreSeal;
use Clegginabox\Airlock\Bridge\Symfony\Seal\SymfonySemaphoreToken;
use Clegginabox\Airlock\Notifier\NullAirlockNotifier;
use Clegginabox\Airlock\Queue\LotteryQueue;
use Clegginabox\Airlock\Queue\Storage\Lottery\RedisLotteryQueueStore;
use Clegginabox\Airlock\QueueAirlock;
use Redis;
use Symfony\Component\Semaphore\Key;
use Symfony\Component\Semaphore\SemaphoreFactory;
use Symfony\Component\Semaphore\Store\RedisStore;

return static function (Redis $redis, array $job, callable $setStatus): void {
    $example = RedisLotteryQueue::NAME->value;
    $clientId = (string) ($job['clientId'] ?? 'anonymous');
    $action = $job['action'] ?? 'hold';
    $holdSeconds = (int) ($job['holdSeconds'] ?? 5);
    $serializedKey = $job['serializedKey'] ?? null;

    if ($action !== 'hold') {
        $setStatus($example, $clientId, [
            'state' => 'error',
            'message' => "Unknown action: {$action}",
            'ts' => time(),
        ]);
        return;
    }

    if (!$serializedKey) {
        $setStatus($example, $clientId, [
            'state' => 'error',
            'message' => 'Missing serialized key in job',
            'ts' => time(),
        ]);
        return;
    }

    $seal = new SymfonySemaphoreSeal(
        factory: new SemaphoreFactory(new RedisStore($redis)),
        resource: RedisLotteryQueue::RESOURCE->value,
        limit: 3,
        ttlInSeconds: 60.0,
        autoRelease: false,
    );

    $graceSecondsEnv = getenv('LOTTERY_GRACE_SECONDS');
    $graceSeconds = is_numeric($graceSecondsEnv) ? max(1, (int) $graceSecondsEnv) : 10;

    $queue = new LotteryQueue(
        new RedisLotteryQueueStore(
            redis: $redis,
            setKey: RedisLotteryQueue::SET_KEY->value,
            candidateKey: RedisLotteryQueue::CANDIDATE_KEY->value,
            candidateTtlSeconds: $graceSeconds
        )
    );

    $airlock = new QueueAirlock($seal, $queue, new NullAirlockNotifier());

    $key = unserialize($serializedKey, ['allowed_classes' => [Key::class]]);
    $token = new SymfonySemaphoreToken($key);

    try {
        $setStatus($example, $clientId, [
            'state' => 'inside',
            'message' => "You're in! Enjoying access for {$holdSeconds}s...",
            'remainingSeconds' => $holdSeconds,
            'ts' => time(),
        ]);

        for ($i = $holdSeconds; $i > 0; $i--) {
            sleep(1);
            $setStatus($example, $clientId, [
                'state' => 'inside',
                'message' => "You're in! {$i}s remaining...",
                'remainingSeconds' => $i,
                'ts' => time(),
            ]);
        }

        $setStatus($example, $clientId, [
            'state' => 'finished',
            'message' => 'Your time is up! Thanks for visiting.',
            'ts' => time(),
        ]);
    } finally {
        $airlock->release($token);
    }
};
