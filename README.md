# Airlock

A distributed mutex with civilized waiting
British-style queuing for your apps. 

Library + optional Symfony bundle to coordinate access to limited resources using a queue, a seal, and an optional notifier.

## Recipes

### Reddit Hug Shield (Traffic Cop)

*The Problem:* You have a standard website (WordPress, Laravel, etc.) on a modest server. A viral link sends 5,000 users at once. Your database locks up, PHP-FPM consumes all RAM, and the server crashes (HTTP 500/502).

*The Solution:* A "Soft Gate" that runs before your framework boots. It allows a safe number of users (e.g., 20) to access the site concurrently, while everyone else sees a lightweight "Server Busy" page that auto-retries.

```php
// prepend.php (Include this at the very top of public/index.php)

use Clegginabox\Airlock\PollingAirlock;
use Clegginabox\Airlock\Seal\SemaphoreSeal;
use Symfony\Component\Semaphore\SemaphoreFactory;
use Symfony\Component\Semaphore\Store\RedisStore;

// 1. Bypass: If user already has a pass, let them through
if (isset($_COOKIE['airlock_pass'])) {
    // Refresh cookie to keep them logged in while active
    setcookie('airlock_pass', $_COOKIE['airlock_pass'], time() + 60, '/', '', true, true);
    return;
}

$seal = new SemaphoreSeal(
    factory: new SemaphoreFactory(new RedisStore()),
    resource: 'traffic_shield',
    limit: 15, // Only 15 heavy PHP scripts running at once
    ttlInSeconds: 60
);

$airlock = new PollingAirlock($seal);

// 3. Attempt Entry
$result = $airlock->enter();

if ($result->isAdmitted()) {
    // Success: Give them a VIP pass for 60 seconds
    setcookie('airlock_pass', $result->getToken(), time() + 60, '/', '', true, true);
    return; // Let the framework load
}

// 4. Failure: Lightweight "Busy" Page
http_response_code(503);
echo "<html><head><meta http-equiv='refresh' content='5'></head><body>";
echo "<h1>Server Busy</h1><p>We are experiencing high traffic. Retrying in 5s...</p>";
echo "</body></html>";
exit; // Stop PHP immediately (saves RAM)
```

### "Ticketmaster" (Fair Waiting Room)

*The Problem:* You are running a high-demand event (e.g., product drop, ticket sale). Fairness is critical. Users must be processed in the exact order they arrived (FIFO), and they need to see their position in line.

*The Solution:* A persistent Queue backed by Redis.

```php
// Controller / API Endpoint

use Clegginabox\Airlock\QueueAirlock;
use Clegginabox\Airlock\Queue\RedisFifoQueue;
// ... imports ...


$seal = new SemaphoreSeal(
    factory: new SemaphoreFactory(new RedisStore()),
    resource: 'ticket_sales',
    limit: 50, // 50 users at a time
    ttlInSeconds: 60
);
$queue = new RedisFifoQueue($redis);
$airlock = new QueueAirlock($seal, $queue, $notifier);

$result = $airlock->enter($userId);

if ($result->isAdmitted()) {
    // The user is IN. 
    // Return the token so the frontend can attach it to future requests.
    return new JsonResponse(['status' => 'admitted', 'token' => $result->getToken()]);
}

// The user is WAITING.
return new JsonResponse([
    'status' => 'queued',
    'position' => $result->getPosition(), // e.g., 452
    'message' => "You are number {$result->getPosition()} in line."
], 202);
```

### Cron Overlap Prevention

*The Problem:* You have a scheduled task (e.g., php bin/console app:generate-report) that runs every minute. Sometimes the report takes 5 minutes to generate. This causes multiple instances to pile up, crashing the server or sending duplicate emails.

*The Solution:*  A blocking lock that ensures only one instance runs at a time. Subsequent runs wait their turn or timeout.

```php
// bin/console app:worker

use Clegginabox\Airlock\Seal\LockSeal;
// ...

$seal = new LockSeal(..., limit: 1);
$airlock = new QueueAirlock($seal, ...);

try {
    // Wait up to 5 seconds to get the lock.
    $airlock->withAdmitted('report-worker', function () {
        echo "Generating Report... (I am the only one running)\n";
        sleep(100);         
    }, timeoutSeconds: 5);

} catch (Exception $e) {
    // Lock held by another process. Exit gracefully.
    echo "Skipping run: Another worker is busy.\n";
}
```

### Throttling Legacy Systems



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

// QueueAirlock or a free for all with PollingAirlock
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
