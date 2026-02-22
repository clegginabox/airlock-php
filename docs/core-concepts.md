# Core Concepts

*Everything has a breaking point.* A database has connection limits. An API has rate limits. A checkout flow falls over if 50,000 people hit it at once.

An airlock sits in front of that thing and makes everyone wait their turn nicely.

## The Three Pieces

Every Airlock is composed of three parts:

| Piece | What It Does | Analogy |
|---|---|---|
| **Seal** | Enforces capacity — how many people fit | The velvet rope |
| **Admission Strategy** | Decides who gets in next | The queue itself |
| **Notifier** | Tells the next person it's their turn | The "your table is ready" buzzer |

Swap one piece, get a different system. Same interface, different behaviour.

## The `Airlock` Interface

Every airlock implementation exposes the same contract:

```php
interface Airlock
{
    public function enter(string $identifier, int $priority = 0): EntryResult;
    public function leave(string $identifier): void;
    public function getPosition(string $identifier): int;
    public function getTopic(string $identifier): string;
}
```

- `enter()` — attempt admission. Returns an `EntryResult` that is either **admitted** (has a token) or **queued** (has a position).
- `leave()` — voluntarily leave the queue or release a slot.
- `getPosition()` — check where you are in the queue.
- `getTopic()` — get the notification topic for real-time updates.

## `EntryResult`

The immutable value object returned by `enter()`. Two possible states:

```php
$result = $airlock->enter($userId);

if ($result->isAdmitted()) {
    $token = $result->getToken(); // SealToken — your proof of admission
} else {
    $position = $result->getPosition(); // int — where you are in line
}
```

## Seals

A Seal is the capacity enforcement primitive. Think of it as the lock on the door.

```php
$token = $seal->tryAcquire();

if ($token !== null) {
    // You're in. Do the work, then release.
}
```

Seals come in flavours:

- **Locking** — mutex, one at a time (`SymfonyLockSeal`)
- **Semaphore** — N concurrent (`SymfonySemaphoreSeal`)
- **Rate Limiting** — X per time window (`SymfonyRateLimiterSeal`)
- **Composite** — combine a lock + rate limiter (both must pass)

Some seals are **releasable** (you can give the slot back early), some are **refreshable** (you can extend your lease), and some are neither (the TTL is the policy).

See [Seals Reference](/reference/seals) for the full breakdown.

## Queues

When capacity is full, users go into a queue. The queue decides who gets the next available slot.

| Queue | Strategy | Fairness |
|---|---|---|
| `FifoQueue` | Strict arrival order | Perfectly fair |
| `LotteryQueue` | Random selection | Not fair at all |
| `AgingLotteryQueue` | Random, but longer waiters get better odds | Fair-ish |
| `PriorityQueue` | Higher priority goes first, FIFO within tier | Fair within class |
| `BackpressureQueue` | Wraps any queue, blocks when system is unhealthy | Adaptive |

See [Queues Reference](/reference/queues) for storage backends and configuration.

## Notifiers

When a slot opens, someone needs to be told. Notifiers handle that.

- `NullAirlockNotifier` — no-op. Use when clients poll, or in tests.
- `MercureAirlockNotifier` — real-time push via Mercure SSE. The buzzer on the table.

## The Flow

```
enter(identifier) →
  [OpportunisticAirlock] seal.tryAcquire() → admitted or rejected
  [RateLimitingAirlock]  seal.tryAcquire() → admitted or rejected
  [QueueAirlock]         queue.add() → position
                         if position == 1 → seal.tryAcquire() → admitted or queued(1)
                         if position > 1  → queued(position)

release(token) →
  [QueueAirlock] seal.release() → queue.peek() → notifier.notify(next)
```

## Optional Capabilities

Not every airlock supports every operation. Two optional interfaces extend the base contract:

- **`ReleasingAirlock`** — `release(SealToken)` — explicitly free a slot before the TTL expires.
- **`RefreshingAirlock`** — `refresh(SealToken, ?ttl): ?SealToken` — extend a lease.

The [Decorators](/reference/decorators-and-events) (`LoggingAirlock`, `EventDispatchingAirlock`) handle these gracefully with runtime `instanceof` checks — no need to wire up different decorators for different airlocks.
