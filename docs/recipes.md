# Recipes

Real-world examples you can drop straight in. Each one follows the same pattern: *The Problem*, *The Solution*, working code.

## Reddit Hug Shield

*The Problem:* You have a standard website on a modest server. A viral link sends 5,000 users at once. Your database locks up, PHP-FPM consumes all RAM, and the server crashes.

*The Solution:* A soft gate that runs before your framework boots. It allows a safe number of users (e.g. 20) to access the site concurrently, while everyone else sees a lightweight "Server Busy" page that auto-retries.

```php
// prepend.php — drop at the very top of public/index.php, before framework boot.
// Requires Redis (or swap to a local lock/semaphore backend for shared hosting).

declare(strict_types=1);

use Clegginabox\Airlock\Bridge\Symfony\Seal\SymfonySemaphoreSeal;
use Clegginabox\Airlock\OpportunisticAirlock;
use Symfony\Component\Semaphore\SemaphoreFactory;
use Symfony\Component\Semaphore\Store\RedisStore;

require_once __DIR__ . '/../vendor/autoload.php';

// Config
$maxConcurrent  = (int)($_ENV['AIRLOCK_MAX_CONCURRENT'] ?? 20);
$sealTtlSeconds = (int)($_ENV['AIRLOCK_SEAL_TTL'] ?? 30);
$passTtlSeconds = (int)($_ENV['AIRLOCK_PASS_TTL'] ?? 60);
$retryMinMs     = (int)($_ENV['AIRLOCK_RETRY_MIN_MS'] ?? 1500);
$retryMaxMs     = (int)($_ENV['AIRLOCK_RETRY_MAX_MS'] ?? 4500);
$cookieSecure   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

// Stable client ID (cookie)
$clientIdCookie = 'airlock_id';
$clientId = $_COOKIE[$clientIdCookie] ?? null;

if (!is_string($clientId) || strlen($clientId) < 16) {
    $clientId = bin2hex(random_bytes(16));
    setcookie($clientIdCookie, $clientId, [
        'expires'  => time() + 60 * 60 * 24 * 30,
        'path'     => '/',
        'secure'   => $cookieSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// Fast bypass if they already have a short-lived pass
$passCookie = 'airlock_pass';

if (isset($_COOKIE[$passCookie]) && is_string($_COOKIE[$passCookie]) && $_COOKIE[$passCookie] !== '') {
    setcookie($passCookie, $_COOKIE[$passCookie], [
        'expires'  => time() + $passTtlSeconds,
        'path'     => '/',
        'secure'   => $cookieSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    return; // let the framework boot
}

// Build the gate
$redis = new Redis();
$redis->connect($_ENV['REDIS_HOST'] ?? '127.0.0.1', (int)($_ENV['REDIS_PORT'] ?? 6379));

$seal = new SymfonySemaphoreSeal(
    factory: new SemaphoreFactory(new RedisStore($redis)),
    resource: 'airlock:traffic_shield',
    limit: $maxConcurrent,
    ttlInSeconds: $sealTtlSeconds,
    autoRelease: false,
);

$airlock = new OpportunisticAirlock($seal);

// Attempt admission
$result = $airlock->enter($clientId);

if ($result->isAdmitted()) {
    $token = $result->getToken();
    setcookie($passCookie, $token, [
        'expires'  => time() + $passTtlSeconds,
        'path'     => '/',
        'secure'   => $cookieSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    return; // let the framework boot
}

// Busy response
http_response_code(503);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('Retry-After: 3');

$retryMs = random_int($retryMinMs, $retryMaxMs);
$retrySeconds = max(1, (int)ceil($retryMs / 1000));
```

Key decisions:

- Uses a **cookie-based client ID**, not IP address (shared IPs would share slots).
- A short-lived **admit pass cookie** lets admitted users browse normally without re-checking every request.
- The busy page uses **jittered retry** to prevent thundering herd on recovery.

## Fair Waiting Room (Ticketmaster-style)

*The Problem:* You're running a high-demand event — product drop, ticket sale. Fairness is critical. Users must be processed in exact arrival order and see their position in line.

*The Solution:* A persistent FIFO queue backed by Redis.

```php
use Clegginabox\Airlock\QueueAirlock;
use Clegginabox\Airlock\Queue\FifoQueue;

$seal = new SymfonySemaphoreSeal(
    factory: new SemaphoreFactory(new RedisStore($redis)),
    resource: 'ticket_sales',
    limit: 50,
    ttlInSeconds: 60,
);

$queue = new FifoQueue($redisStore);
$airlock = new QueueAirlock($seal, $queue, $notifier);

$result = $airlock->enter($userId);

if ($result->isAdmitted()) {
    return new JsonResponse([
        'status' => 'admitted',
        'token' => (string) $result->getToken(),
    ]);
}

return new JsonResponse([
    'status' => 'queued',
    'position' => $result->getPosition(),
    'message' => "You are number {$result->getPosition()} in line.",
], 202);
```

## Cron Overlap Prevention

*The Problem:* A scheduled task runs every minute but sometimes takes 5 minutes. Multiple instances pile up, crashing the server or sending duplicate emails.

*The Solution:* A blocking lock that ensures only one instance runs at a time.

```php
use Clegginabox\Airlock\Bridge\Symfony\Seal\SymfonyLockSeal;
use Clegginabox\Airlock\QueueAirlock;

$seal = new SymfonyLockSeal(/* ... */);
$airlock = new QueueAirlock($seal, /* ... */);

try {
    $airlock->withAdmitted('report-worker', function () {
        echo "Generating Report... (I am the only one running)\n";
        sleep(100);
    }, timeoutSeconds: 5);
} catch (Exception $e) {
    echo "Skipping run: Another worker is busy.\n";
}
```

## Throttling Legacy Systems

*The Problem:* You have a brittle upstream (ERP, SOAP service, vendor API) that falls over if more than 5 requests hit it at once. Your app can generate hundreds of concurrent calls.

*The Solution:* A semaphore gate in front of the outbound call. `withAdmitted()` keeps the critical section safe and always releases.

```php
$seal = new SymfonySemaphoreSeal(
    factory: new SemaphoreFactory(new RedisStore($redis)),
    resource: 'legacy_erp',
    limit: 5,
    ttlInSeconds: 30,
);

$airlock = new OpportunisticAirlock($seal);

$response = $airlock->withAdmitted('erp-call', function (string $token) use ($httpClient) {
    return $httpClient->request('GET', 'https://legacy.example.com/api/orders');
}, timeoutSeconds: 10);
```

## Laravel Single Flight

*The Problem:* Users double-click "Pay now" or spam "Export CSV". You need "only one per user per action".

*The Solution:* Laravel bridge seal + `withAdmitted()`.

```php
// In a Laravel controller
$key = "pay-now:{$user->id}:{$order->id}";

return Airlock::withAdmitted($key, function () use ($order) {
    $this->payments->charge($order);
    return response()->json(['ok' => true]);
}, timeoutSeconds: 2);
```

If it can't get the lock quickly, return 409 Conflict or 429 with a friendly message.

## Cooldown Gate

*The Problem:* "Resend 2FA code" must be allowed once every 30 seconds. You don't want early release to bypass the rule.

*The Solution:* A non-releasable seal using a TTL-based key. The TTL *is* the policy.

```php
$seal = new CooldownSeal(
    redis: $redis,
    resource: "2fa:resend:{$userId}",
    ttlInSeconds: 30,
);

$airlock = new OpportunisticAirlock($seal);

$result = $airlock->enter($userId);

if (!$result->isAdmitted()) {
    return new JsonResponse(['error' => 'Try again shortly'], 429);
}

$this->twoFactor->sendCode($userId);

return new JsonResponse(['ok' => true]);
```

No `release()` concept. The TTL is the policy.

## Aging Lottery

*The Problem:* FIFO is fair but brittle — dead heads, reconnects, and slow clients can stall progress. Lottery is lively but unfair.

*The Solution:* Aging lottery — the longer you wait, the more likely you are to be picked next.

```php
$queue = new RedisAgingLotteryQueue(
    redis: $redis,
    key: 'drop_queue',
    baseTickets: 1,
    ticketsPerSecond: 0.1, // every 10s waiting = +1 ticket
    maxTickets: 50,
);

$airlock = new QueueAirlock($seal, $queue, $notifier);

$result = $airlock->enter($userId);
```

Great for public waiting rooms where strict order isn't required.

## Graceful Admission (Reservation + Claim)

*The Problem:* You push "you're next" via Mercure, but the user may be offline. You can't hold capacity forever.

*The Solution:* A reservation window (e.g. 20s) + explicit claim.

```php
// When a slot frees:
$head = $queue->peek();
$reservations->reserve($head, ttl: 20);
$notifier->notify($head, "You're up! Claim within 20s");

// Client calls /claim:
if (!$reservations->isReservedFor($userId)) {
    return new JsonResponse(['error' => 'missed'], 409);
}

$token = $seal->tryAcquire(); // now take real capacity
return new JsonResponse(['token' => (string) $token]);
```
