# Recipes

## Reddit Hug Shield (Traffic Cop)

*The Problem:* You have a standard website (WordPress, Laravel, etc.) on a modest server. A viral link sends 5,000 users at once. Your database locks up, PHP-FPM consumes all RAM, and the server crashes (HTTP 500/502).

*The Solution:* A "Soft Gate" that runs before your framework boots. It allows a safe number of users (e.g., 20) to access the site concurrently, while everyone else sees a lightweight "Server Busy" page that auto-retries.

```php
// prepend.php (Include this at the very top of public/index.php)

<?php
// prepend.php
//
// Drop this at the very top of public/index.php (before framework boot).
// Goal: cap concurrent "expensive" requests so PHP-FPM/DB don't implode under a spike.
//
// Requires: Redis (or swap to a local lock/semaphore backend for shared hosting).
// Notes:
// - Uses a stable client id cookie (NOT IP address).
// - Uses a short-lived "admit pass" cookie so admitted users can browse normally.
// - Busy page retries with jitter (prevents thundering herd).

declare(strict_types=1);

use Clegginabox\Airlock\OpportunisticAirlock;
use Clegginabox\Airlock\Seal\SemaphoreSeal;
use Symfony\Component\Semaphore\SemaphoreFactory;
use Symfony\Component\Semaphore\Store\RedisStore;

require_once __DIR__ . '/../vendor/autoload.php';

// -----------------------
// 0) Config
// -----------------------
$maxConcurrent   = (int)($_ENV['AIRLOCK_MAX_CONCURRENT'] ?? 20);
$sealTtlSeconds  = (int)($_ENV['AIRLOCK_SEAL_TTL'] ?? 30);     // lease safety net
$passTtlSeconds  = (int)($_ENV['AIRLOCK_PASS_TTL'] ?? 60);     // browser "admitted" window
$retryMinMs      = (int)($_ENV['AIRLOCK_RETRY_MIN_MS'] ?? 1500);
$retryMaxMs      = (int)($_ENV['AIRLOCK_RETRY_MAX_MS'] ?? 4500);

$cookieSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

// -----------------------
// 1) Stable client id (cookie)
// -----------------------
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

// -----------------------
// 2) Fast bypass if they already have a short-lived pass
// -----------------------
$passCookie = 'airlock_pass';

if (isset($_COOKIE[$passCookie]) && is_string($_COOKIE[$passCookie]) && $_COOKIE[$passCookie] !== '') {
    // Extend pass while active (keeps the browsing experience smooth)
    setcookie($passCookie, $_COOKIE[$passCookie], [
        'expires'  => time() + $passTtlSeconds,
        'path'     => '/',
        'secure'   => $cookieSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    return; // let the framework boot
}

// -----------------------
// 3) Build the gate (keep it lightweight)
// -----------------------
$redis = new Redis();
$redis->connect($_ENV['REDIS_HOST'] ?? '127.0.0.1', (int)($_ENV['REDIS_PORT'] ?? 6379));

$seal = new SemaphoreSeal(
    factory: new SemaphoreFactory(new RedisStore($redis)),
    resource: 'airlock:traffic_shield',
    limit: $maxConcurrent,
    ttlInSeconds: $sealTtlSeconds,
    autoRelease: false
);

$airlock = new OpportunisticAirlock($seal);

// -----------------------
// 4) Attempt admission
// -----------------------
$result = $airlock->enter($clientId);

if ($result->isAdmitted()) {
    // Give them a short "pass" so they can make multiple requests without re-checking every time.
    // Prefer cookie over query string to avoid leaking tokens via referrers/logs.
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

// -----------------------
// 5) Busy response (cheap)
// -----------------------
http_response_code(503);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('Retry-After: 3');

// Simple jittered retry (1.5s–4.5s by default).
$retryMs = random_int($retryMinMs, $retryMaxMs);
$retrySeconds = max(1, (int)ceil($retryMs / 1000));

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta http-equiv="refresh" content="<?= htmlspecialchars((string)$retrySeconds, ENT_QUOTES) ?>">
  <title>High traffic</title>
  <style>
    body { font-family: system-ui, sans-serif; padding: 2rem; line-height: 1.4; }
    .box { max-width: 42rem; margin: 0 auto; }
    .muted { opacity: 0.75; }
    code { background: #f3f3f3; padding: 0.15rem 0.35rem; border-radius: 6px; }
  </style>
</head>
<body>
  <div class="box">
    <h1>High traffic</h1>
    <p>We’re limiting concurrent visitors so the site stays up. You’ll be let in automatically.</p>
    <p class="muted">Retrying in ~<?= htmlspecialchars((string)$retrySeconds, ENT_QUOTES) ?>s…</p>
    <noscript>
      <p class="muted">JavaScript is disabled — this page will refresh automatically.</p>
    </noscript>
    <script>
      // Jittered refresh (slightly nicer than fixed meta refresh).
      // Keep it boring: no tight loops.
      const ms = <?= (int)$retryMs ?>;
      setTimeout(() => location.reload(), ms);
    </script>
  </div>
</body>
</html>
<?php
exit;
```

## "Ticketmaster" (Fair Waiting Room)

*The Problem:* You are running a high-demand event (e.g., product drop, ticket sale). Fairness is critical. Users must be processed in the exact order they arrived (FIFO), and they need to see their position in line.

*The Solution:* A persistent FIFO Queue backed by Redis.

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

## Cron Overlap Prevention

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

## Throttling Legacy Systems (API Shield)

The Problem: You have a brittle upstream (ERP, SOAP service, vendor API) that falls over if more than 5 requests hit it at once. Your app/worker can generate hundreds of concurrent calls.

The Solution: A semaphore gate in front of the outbound call. In a long-running worker, withAdmitted() keeps the critical section safe and always releases.

```php
// In a worker / CLI command (sync version)
$seal = new SemaphoreSeal(
    factory: new SemaphoreFactory(new RedisStore($redis)),
    resource: 'legacy_erp',
    limit: 5,
    ttlInSeconds: 30
);

$airlock = new OpportunisticAirlock($seal);

$response = $airlock->withAdmitted('erp-call', function (string $token) use ($httpClient) {
    return $httpClient->request('GET', 'https://legacy.example.com/api/orders');
}, timeoutSeconds: 10);
```

## Async Fan-Out Worker (5 APIs at once, but only N in flight)

The Problem: A Temporal activity calls 5 upstream APIs concurrently for each job. Under load, you melt your own network or the vendors.

The Solution: Async HTTP + an Airlock semaphore that caps total in-flight calls across the whole worker process.

```php
// Pseudocode shape (React/Amp style)
// Use AirlockAsync + async HTTP client to cap concurrency.
$seal = new SemaphoreSeal(... resource: 'outbound_http', limit: 50, ttlIn1Seconds: 20);
$airlock = new AirlockAsync(new OpportunisticAirlock($seal));

$jobs = array_map(fn($url) => function () use ($url, $airlock, $http) {
    return $airlock->withAdmittedAsync('http', function (string $token) use ($http, $url) {
        return $http->get($url); // returns Promise
    }, timeoutSeconds: 5);
}, $urls);

$results = Promise\all($jobs);
```
(The “magic” is: you can queue 10,000 scheduled calls, but only 50 run at once.)

## Laravel “Single Flight” (Idempotent Controller Action)

The Problem: Users double-click “Pay now” or spam “Export CSV”. You need “only one per user per action”.

The Solution: Laravel bridge seal (Cache lock) + withAdmitted().

```php
// In a Laravel controller
$key = "pay-now:{$user->id}:{$order->id}";

return Airlock::withAdmitted($key, function () use ($order) {
    $this->payments->charge($order);
    return response()->json(['ok' => true]);
}, timeoutSeconds: 2);
```
If it can’t get the lock quickly, return 409 Conflict / 429 with a friendly message.

## Cooldown Gate (Non-releasable, e.g. “Resend 2FA code”)

The Problem: “Resend code” must be allowed once every 30 seconds. You do not want early release to bypass the rule.

The Solution: CooldownSeal (non-releasable) using Redis SET NX EX.

```php
// POST /2fa/resend
$seal = new CooldownSeal(
    redis: $redis,
    resource: "2fa:resend:{$userId}",
    ttlInSeconds: 30
);

$airlock = new OpportunisticAirlock($seal);

$result = $airlock->enter($userId);

if (!$result->isAdmitted()) {
    return new JsonResponse([
        'error' => 'Try again shortly'
    ], 429);
}

$this->twoFactor->sendCode($userId);

return new JsonResponse(['ok' => true]);
```
No release() concept. The TTL is the policy.

## Aging Lottery (Fair-ish, non-blocking under disconnects)

The Problem: FIFO is “fair” but brittle: dead heads, reconnects, and slow clients can stall progress. Lottery is lively but unfair.

The Solution: Aging lottery: the longer you wait, the more likely you are to be picked next.

```php
$queue = new RedisAgingLotteryQueue(
    redis: $redis,
    key: 'drop_queue',
    baseTickets: 1,
    ticketsPerSecond: 0.1, // every 10s waiting = +1 ticket
    maxTickets: 50
);

$airlock = new QueueAirlock($seal, $queue, $notifier);

$result = $airlock->enter($userId);
```
Great for public waiting rooms where strict order isn’t required.

### Graceful Admission (Reservation + Claim)

The Problem: You push “you’re next” via Mercure, but they may be offline. You can’t hold capacity forever.

The Solution: Reservation window (20s) + claim.
