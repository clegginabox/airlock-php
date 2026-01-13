# Airlock

A distributed mutex with civilized waiting

___British-style queuing for your code and infra___

## The Core Idea

An Airlock is composed of:
- a Seal (how capacity is enforced)
- an Admission Strategy (who gets in next)
- optional Notifier (how waiters are told)

Swap one piece, get a different system.

## Best-Effort / Anti-Hug Gate

Behaviour:
- No fairness guarantees
- First request to hit free capacity wins
- Fast, simple, resilient

```php
use Clegginabox\Airlock\OpportunisticAirlock;
use Clegginabox\Airlock\Seal\SemaphoreSeal;
use Symfony\Component\Semaphore\SemaphoreFactory;
use Symfony\Component\Semaphore\Store\RedisStore;

$redis = new Redis();
$redis->connect('127.0.0.1');

// Allow up to N concurrent “expensive” requests.
$seal = new SemaphoreSeal(
    factory: new SemaphoreFactory(new RedisStore($redis)),
    resource: 'site_capacity',
    limit: 20,
    ttlInSeconds: 30,
    autoRelease: false,
);

$airlock = new OpportunisticAirlock($seal);

$result = $airlock->enter($clientId);
```

## Strict Fairness (FIFO)

Behaviour:
- Exact arrival order
- Deterministic
- Dead heads must be handled explicitly

```php
use Clegginabox\Airlock\QueueAirlock;
use Clegginabox\Airlock\Queue\RedisFifoQueue;

$seal = new SemaphoreSeal(... limit: 50);
$queue = new RedisFifoQueue($redis);

$airlock = new QueueAirlock($seal, $queue);

$result = $airlock->enter($userId);
```

## Lottery (Fast, Unfair)
Behaviour:
- No ordering
- High throughput
- Self-healing under disconnects

```php
use Clegginabox\Airlock\QueueAirlock;
use Clegginabox\Airlock\Queue\RedisLotteryQueue;

$seal = new SemaphoreSeal(... limit: 50);
$queue = new RedisLotteryQueue($redis);

$airlock = new QueueAirlock($seal, $queue);
```

## Aging Lottery (Fair-ish)
Behaviour:
- No ordering
- High throughput
- Self-healing under disconnects

```php
use Clegginabox\Airlock\QueueAirlock;
use Clegginabox\Airlock\Queue\RedisLotteryQueue;

$seal = new SemaphoreSeal(... limit: 50);
$queue = new RedisLotteryQueue($redis);

$airlock = new QueueAirlock($seal, $queue);
```

## Priority Queue (Logged-in Users First)

Behaviour:
- Higher priority users jump ahead
- FIFO within same priority tier
- Guests wait, members skip the line

```php
use Clegginabox\Airlock\QueueAirlock;
use Clegginabox\Airlock\Queue\RedisPriorityQueue;

$seal = new SemaphoreSeal(... limit: 50);
$queue = new RedisPriorityQueue($redis);

$airlock = new QueueAirlock($seal, $queue);

// Priority: higher = better. Logged-in users get priority 10, guests get 0.
$priority = $user->isLoggedIn() ? 10 : 0;

$result = $airlock->enter($userId, $priority);
```

Tiered priorities work too - VIPs at 100, paid members at 50, free users at 10, anonymous at 0.

## Singleton / Idempotency

Behaviour:
- Exactly one at a time
- No queue UI
- Perfect for cron & user actions

```php
use Clegginabox\Airlock\OpportunisticAirlock;
use Clegginabox\Airlock\Seal\LockSeal;

$seal = new LockSeal();
$airlock = new OpportunisticAirlock($seal);

$airlock->withAdmitted('job:invoice', function () {
    // guaranteed single-flight
});
```



```php
// when a slot frees:
$head = $queue->peek();
$reservations->reserve($head, ttl: 20);
$notifier->notify($head, "You’re up! Claim within 20s");

// client calls /claim:
if (!$reservations->isReservedFor($userId)) {
    return new JsonResponse(['error' => 'missed'], 409);
}

$token = $seal->tryAcquire(); // now take real capacity
return new JsonResponse(['token' => $token]);
```
This solves “dead head” cleanly.

### Multi-Tenant Throttle (Per Customer Limits)

The Problem: One noisy customer can starve others. You want per-tenant concurrency caps.

The Solution: Resource namespacing.

```php
$resource = "tenant:{$tenantId}:imports";

$seal = new SemaphoreSeal(... resource: $resource, limit: 2, ttlInSeconds: 60);
$airlock = new OpportunisticAirlock($seal);

$airlock->withAdmitted($tenantId, function () use ($tenantId) {
    $this->runImport($tenantId);
});
```

### “Fail Closed” Maintenance Gate (Kill-switch)

The Problem: You’re being hammered or doing maintenance. You want to shut the expensive origin off instantly.

The Solution: Airlock that checks a toggle key first.

```php
if ($redis->get('airlock:maintenance') === '1') {
    http_response_code(503);
    echo "Maintenance";
    exit;
}

$result = $airlock->enter($id);
```
It’s simple, but extremely useful.

## PHP

```php

use Clegginabox\Airlock\Notifier\NullAirlockNotifier;
use Clegginabox\Airlock\Seal\LockSeal;
use Clegginabox\Airlock\Seal\SemaphoreSeal;
use Clegginabox\Airlock\QueueAirlock;
use Clegginabox\Airlock\Queue\RedisFifoQueue;
use Redis;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Semaphore\SemaphoreFactory;

$redis = new Redis();
$redis->connect('172.17.0.2');

// Semaphore allows more than 1 user to enter at a time
$seal = new SemaphoreSeal(
    factory: new SemaphoreFactory(
        new \Symfony\Component\Semaphore\Store\RedisStore($redis)
    ),
    resource: 'my_airlock',
    limit: 10,
    weight: 1,
    ttlInSeconds: 900,
    autoRelease: false
);

$seal = new LockSeal(
    factory: new LockFactory(
        new \Symfony\Component\Lock\Store\RedisStore($redis)
    ),
    resource: 'my_airlock',
    ttlInSeconds: 900,
    autoRelease: false
);

// RedisFifoQueue (fair) or RedisLotteryQueue (random)
$queue = new RedisFifoQueue($redis);

// NullAirlockNotifier or MercureAirlockNotifier
$notifier = new NullAirlockNotifier();

// QueueAirlock or a free for all with OpportunisticAirlock
$airlock = new QueueAirlock(
    seal: $seal,
    queue: $queue,
    notifier: $notifier,
    topicPrefix: '/my_airlock'
);

// Try and enter the airlock
$result = $airlock->enter($userId);

if ($result->isAdmitted()) {
    // Get the seal token
    $token = $result->getToken();
    
    // ... Do your protected work ...
    
    // Heartbeat: Extend lease by another 5 minutes
    $airlock->refresh($token, 300);
    
    // Done? Let the next person in!
    $airlock->release($token);
} else {
    // Access Denied! Join the line
    $queuePosition = $airlock->getPosition($userId);
    
    // Bored of waiting?
    $airlock->leave($userId);
}
```

## Symfony bundle

Register the bundle (if not using Flex):

```php
// config/bundles.php
return [
    Clegginabox\Airlock\Bridge\Symfony\AirlockBundle::class => ['all' => true],
];
```

Example config for a single airlock:

```yaml
# config/packages/airlock.yaml
airlock:
  service_id: waiting_room.workflow
  alias: waiting_room.workflow
  topic_prefix: /workflow_waiting_room
  seal:
    type: semaphore
    semaphore:
      factory: semaphore.workflow.factory
      resource: workflow_waiting_room
      limit: '%env(int:WORKFLOW_SEMAPHORE_LIMIT)%'
      ttl_in_seconds: '%env(int:WORKFLOW_SEMAPHORE_TTL)%'
  queue:
    type: redis_fifo
    redis_fifo:
      redis: redis
  notifier:
    type: mercure
    mercure:
      hub: mercure.hub.default
```

When only one airlock is configured, the bundle exposes interface aliases:

- `Clegginabox\Airlock\AirlockInterface`
- `Clegginabox\Airlock\Seal\SealInterface`
- `Clegginabox\Airlock\Queue\QueueInterface`
- `Clegginabox\Airlock\Notifier\AirlockNotifierInterface`

## Multiple airlocks

Define named airlocks under `airlocks:`. Each airlock creates:

- `airlock.<name>` (or `service_id` if set)
- `airlock.<name>.seal`
- `airlock.<name>.queue`
- `airlock.<name>.notifier`

```yaml
# config/packages/airlock.yaml
airlock:
  airlocks:
    workflow:
      topic_prefix: /workflow_waiting_room
      seal:
        semaphore:
          factory: semaphore.workflow.factory
          resource: workflow_waiting_room
          limit: '%env(int:WORKFLOW_SEMAPHORE_LIMIT)%'
          ttl_in_seconds: '%env(int:WORKFLOW_SEMAPHORE_TTL)%'
      queue:
        type: redis_fifo
        redis_fifo:
          redis: redis
      notifier:
        type: mercure
        mercure:
          hub: mercure.hub.default

    onboarding:
      topic_prefix: /onboarding_waiting_room
      queue:
        type: redis_lottery
      notifier:
        type: null
```

When multiple airlocks are configured, interface aliases are not set to avoid ambiguity.
