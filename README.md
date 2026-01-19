# airlock-php - Distributed locking with manners

[![Codacy Badge](https://app.codacy.com/project/badge/Grade/cdc12dbceac04dc8bbece4012222cd3d)](https://app.codacy.com/gh/clegginabox/airlock-php/dashboard?utm_source=gh&utm_medium=referral&utm_content=&utm_campaign=Badge_grade)
[![Codacy Badge](https://app.codacy.com/project/badge/Coverage/cdc12dbceac04dc8bbece4012222cd3d)](https://app.codacy.com/gh/clegginabox/airlock-php/dashboard?utm_source=gh&utm_medium=referral&utm_content=&utm_campaign=Badge_coverage)
![PHPCS](https://img.shields.io/github/actions/workflow/status/clegginabox/airlock-php/tests.yaml?label=phpcs)
![PHPUnit](https://img.shields.io/github/actions/workflow/status/clegginabox/airlock-php/tests.yaml?label=tests)
![E2E](https://img.shields.io/github/actions/workflow/status/clegginabox/airlock-php/tests.yaml?label=e2e)

<img width="830" height="453" alt="airlock-php-red" src="https://github.com/user-attachments/assets/361fb9d2-00a4-4a11-b8cf-cde4fc951b9f" />

British-style queuing for your code and infrastructure. First come, first served. As it should be.

(Not to be confused with a message queue. Airlock doesn’t process messages — it just decides who’s coming in and who’s staying outside in the rain.)

> [!CAUTION]
> **Very Early Work in Progress** - This library is under active development and not yet production-ready. APIs will change, many implementations are stubs and test coverage is incomplete. Use at your own risk, contributions welcome.

## The Core Idea

*Everything has a breaking point.* A database has connection limits. An API has rate limits. A checkout flow falls over if 50,000 people hit it at once.
An airlock sits in front of that thing and makes everyone wait their turn nicely.

Every Airlock is composed of:
- A Seal — how capacity is enforced (the velvet rope)
- An Admission Strategy — who gets in next (the queue itself)
- An optional Notifier — how waiters are told it’s their turn (the “your table is ready” buzzer)

Swap one piece, get a different system. Same interface, different behaviour. Dead simple.    

## Best-Effort / Anti-Hug Gate

No fairness guarantees. First request to hit free capacity wins. If two requests arrive at the same time, one gets in and one doesn’t — and there’s no predicting which.

Fast, simple, resilient. Perfect for protecting an endpoint from the hug of death when you don’t care who gets through, just how many.

```php
use Clegginabox\Airlock\Bridge\Symfony\Seal\SymfonySemaphoreSeal;use Clegginabox\Airlock\OpportunisticAirlock;use Symfony\Component\Semaphore\SemaphoreFactory;use Symfony\Component\Semaphore\Store\RedisStore;

$redis = new Redis();
$redis->connect('127.0.0.1');

// Allow up to N concurrent “expensive” requests.
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

## Strict Fairness (FIFO)

The proper British queue. Exact arrival order. Deterministic. No cutting, no exceptions.

If someone in front of you wanders off to browse the shop, the whole queue waits. Dead heads must be handled explicitly — the system won’t assume they’ve left just because they’ve gone quiet.

```php
use Clegginabox\Airlock\QueueAirlock;
use Clegginabox\Airlock\Queue\RedisFifoQueue;

$seal = new SemaphoreSeal(... limit: 50);
$queue = new RedisFifoQueue($redis);

$airlock = new QueueAirlock($seal, $queue);

$result = $airlock->enter($userId);
```

## Lottery (Fast, Unfair)

No ordering. High throughput. Self-healing under disconnects.

The Ryanair boarding approach. Priority boarding means nowt when everyone’s already elbowing toward the gate. If someone disconnects, they simply drop out of the draw — no cleanup required.

```php
use Clegginabox\Airlock\QueueAirlock;
use Clegginabox\Airlock\Queue\RedisLotteryQueue;

$seal = new SemaphoreSeal(... limit: 50);
$queue = new RedisLotteryQueue($redis);

$airlock = new QueueAirlock($seal, $queue);
```

## Aging Lottery (Fair-ish)

Same as the lottery, but the longer you wait, the better your odds. Eventually even the unluckiest punter gets through.

The “I’ve been waiting ages, surely it’s my turn” system. Not strictly fair, but feels fairer — and sometimes that’s what matters.

```php
use Clegginabox\Airlock\QueueAirlock;
use Clegginabox\Airlock\Queue\RedisLotteryQueue;

$seal = new SemaphoreSeal(... limit: 50);
$queue = new RedisAgingLotteryQueue($redis);

$airlock = new QueueAirlock($seal, $queue);
```

## Priority Queue (Logged-in Users First)

Higher priority users jump ahead. FIFO within the same tier. Guests wait, members skip the line.

The members’ entrance at the club. You’re still queuing, just… better.

```php
use Clegginabox\Airlock\QueueAirlock;
use Clegginabox\Airlock\Queue\PriorityQueue;

$seal = new SemaphoreSeal(... limit: 50);
$queue = new PriorityQueue($redis);

$airlock = new QueueAirlock($seal, $queue);

// Priority: higher = better. Logged-in users get priority 10, guests get 0.
$priority = $user->isLoggedIn() ? 10 : 0;

$result = $airlock->enter($userId, $priority);
```

Tiered priorities work too - VIPs at 100, paid members at 50, free users at 10, anonymous at 0.

## Singleton / Idempotency

Exactly one at a time. No queue, no waiting room UI — just a simple “is someone already doing this?” check.

Perfect for cron jobs that must never overlap, or user actions that shouldn’t fire twice if they double-click. The “we’re not having two of those” approach.

```php
use Clegginabox\Airlock\Bridge\Symfony\Seal\SymfonyLockSeal;use Clegginabox\Airlock\OpportunisticAirlock;

$seal = new SymfonyLockSeal();
$airlock = new OpportunisticAirlock($seal);

$airlock->withAdmitted('job:invoice', function () {
    // guaranteed single-flight
});
```

## When Not to Use Airlock

Airlock is not trying to be Cloudflare. If you’re selling Glastonbury tickets to the entire country at once, you need infrastructure with a budget bigger than this library’s test coverage.

Airlock is for the stuff in between. The internal dashboard that falls over when someone sends a company-wide email. The checkout flow that can’t handle a flash sale. The webhook endpoint that your biggest customer keeps hammering.

It’s probably not the right fit if:
- **You just want to return 429s** — A rate limiter is simpler. Airlock assumes callers are willing to wait their turn.
- **Waiting is not an option** — If requests must fail immediately, you want fail-fast guards, not admission control.

## Plans/Ideas/Roadmap

- Symfony integration (Lock, Semaphore & RateLimiter)
- Laravel integration (Lock & RateLimiter)
- AMPHP integration (amphp/sync)
- Extend Symfony Semaphore with more storage backends
- Cloudflare Durable Objects integration
- Composite Seal (combine RateLimiter + Lock/Semaphore)
