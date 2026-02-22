# Strategies

Airlock ships with three airlock implementations. Same interface, different trade-offs. Pick the one that fits your problem.

## Best-Effort / Anti-Hug Gate

**Class:** `OpportunisticAirlock`
**Implements:** `Airlock`, `ReleasingAirlock`

No fairness guarantees. First request to hit free capacity wins. If two requests arrive at the same time, one gets in and one doesn't — and there's no predicting which.

Fast, simple, resilient. Perfect for protecting an endpoint from the hug of death when you don't care *who* gets through, just *how many*.

```php
use Clegginabox\Airlock\Bridge\Symfony\Seal\SymfonySemaphoreSeal;
use Clegginabox\Airlock\OpportunisticAirlock;
use Symfony\Component\Semaphore\SemaphoreFactory;
use Symfony\Component\Semaphore\Store\RedisStore;

$redis = new Redis();
$redis->connect('127.0.0.1');

$seal = new SymfonySemaphoreSeal(
    factory: new SemaphoreFactory(new RedisStore($redis)),
    resource: 'site_capacity',
    limit: 20,
    ttlInSeconds: 30,
    autoRelease: false,
);

$airlock = new OpportunisticAirlock($seal);

$result = $airlock->enter($clientId);
```

**When to use:** Traffic spikes, hug-of-death protection, anywhere "some get through, the rest wait" is good enough.

## Rate Limiting

**Class:** `RateLimitingAirlock`
**Implements:** `Airlock`

Pure rate limiting. No release, no queue. The seal enforces a rate (e.g. 100 requests per minute) and that's it.

```php
use Clegginabox\Airlock\RateLimitingAirlock;
use Clegginabox\Airlock\Bridge\Symfony\Seal\SymfonyRateLimiterSeal;

$seal = new SymfonyRateLimiterSeal(/* ... */);
$airlock = new RateLimitingAirlock($seal);

$result = $airlock->enter($clientId);
```

**When to use:** API rate limiting, cooldown gates, anywhere the policy is "X per time window" and there's nothing to release.

## Queued Admission

**Class:** `QueueAirlock`
**Implements:** `Airlock`, `ReleasingAirlock`

Fair queuing. Users are added to a queue and admitted in order (or by lottery, or by priority — depending on the queue implementation you wire in).

When someone leaves, the next person in the queue is notified.

```php
use Clegginabox\Airlock\QueueAirlock;
use Clegginabox\Airlock\Queue\FifoQueue;

$airlock = new QueueAirlock($seal, $queue, $notifier);

$result = $airlock->enter($userId);
```

The `QueueAirlock` is the most flexible — the queue strategy is pluggable. Wire in a different queue and the behaviour changes:

### Strict FIFO

The proper British queue. Exact arrival order. Deterministic. No cutting, no exceptions.

If someone in front of you wanders off to browse the shop, the whole queue waits. Dead heads must be handled explicitly — the system won't assume they've left just because they've gone quiet.

```php
$queue = new FifoQueue($store); // InMemoryFifoQueueStore or RedisFifoQueueStore
$airlock = new QueueAirlock($seal, $queue, $notifier);
```

### Lottery

No ordering. High throughput. Self-healing under disconnects.

The Ryanair boarding approach. Priority boarding means nowt when everyone's already elbowing toward the gate. If someone disconnects, they simply drop out of the draw — no cleanup required.

```php
$queue = new LotteryQueue($store); // RedisLotteryQueueStore
$airlock = new QueueAirlock($seal, $queue, $notifier);
```

### Aging Lottery

Same as the lottery, but the longer you wait, the better your odds. Eventually even the unluckiest punter gets through.

The "I've been waiting ages, surely it's my turn" system. Not strictly fair, but *feels* fairer — and sometimes that's what matters.

```php
$queue = new RedisAgingLotteryQueue(
    redis: $redis,
    key: 'drop_queue',
    baseTickets: 1,
    ticketsPerSecond: 0.1, // every 10s waiting = +1 ticket
    maxTickets: 50,
);

$airlock = new QueueAirlock($seal, $queue, $notifier);
```

### Priority Queue

Higher priority users jump ahead. FIFO within the same tier. Guests wait, members skip the line.

The members' entrance at the club. You're still queuing, just... better.

```php
$queue = new PriorityQueue($redis);
$airlock = new QueueAirlock($seal, $queue);

// Priority: higher = better. Logged-in users get priority 10, guests get 0.
$priority = $user->isLoggedIn() ? 10 : 0;
$result = $airlock->enter($userId, $priority);
```

Tiered priorities work too — VIPs at 100, paid members at 50, free users at 10, anonymous at 0.

### Backpressure Queue

Wraps any queue. Blocks admission entirely when system health drops below a threshold.

```php
$healthChecker = new MyHealthChecker(); // implements HealthCheckerInterface
$innerQueue = new FifoQueue($store);
$queue = new BackpressureQueue($innerQueue, $healthChecker, threshold: 0.5);

$airlock = new QueueAirlock($seal, $queue, $notifier);
```

If `$healthChecker->getScore()` returns below `0.5`, nobody new gets in — regardless of available capacity.

## Singleton / Idempotency

Not a separate class — just `OpportunisticAirlock` with a lock seal and `withAdmitted()`.

Exactly one at a time. No queue, no waiting room UI — just a simple "is someone already doing this?" check.

Perfect for cron jobs that must never overlap, or user actions that shouldn't fire twice if they double-click. The "we're not having two of those" approach.

```php
use Clegginabox\Airlock\Bridge\Symfony\Seal\SymfonyLockSeal;
use Clegginabox\Airlock\OpportunisticAirlock;

$seal = new SymfonyLockSeal(/* ... */);
$airlock = new OpportunisticAirlock($seal);

$airlock->withAdmitted('job:invoice', function () {
    // guaranteed single-flight
});
```

## When Not to Use Airlock

Airlock is not trying to be Cloudflare. If you're selling Glastonbury tickets to the entire country at once, you need infrastructure with a budget bigger than this library's test coverage.

Airlock is for the stuff in between. The internal dashboard that falls over when someone sends a company-wide email. The checkout flow that can't handle a flash sale. The webhook endpoint that your biggest customer keeps hammering.

It's probably not the right fit if:

- **You just want to return 429s** — a rate limiter is simpler. Airlock assumes callers are willing to wait their turn.
- **Waiting is not an option** — if requests must fail immediately, you want fail-fast guards, not admission control.
