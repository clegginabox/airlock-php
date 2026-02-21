# Airlock

A PHP library for managing distributed capacity control and queuing. It sits in front of capacity-limited resources, controlling how many users can access them simultaneously and queuing the rest.

## Quick Commands

```bash
# Tests (unit only — integration tests need Docker/Redis)
vendor/bin/phpunit tests/Unit/

# Static analysis (level 8)
vendor/bin/phpstan analyse --memory-limit=512M

# Coding standards (PSR-12 + Slevomat)
vendor/bin/phpcs
vendor/bin/phpcbf              # auto-fix

# Rector (dry-run by default)
vendor/bin/rector process --dry-run

# Docker-based full test run (includes integration tests with Testcontainers)
docker run --rm -v $(pwd):/app -v /var/run/docker.sock:/var/run/docker.sock airlock php vendor/bin/phpunit
```

Composer requires `--ignore-platform-reqs` locally (ext-redis, ext-memcached, ext-zookeeper are Docker-only).

## Architecture

Namespace: `Clegginabox\Airlock` — PSR-4 under `src/`.

### Core Interfaces

- **`Airlock`** — main contract: `enter(identifier, priority): EntryResult`, `leave(identifier)`, `getPosition(identifier)`, `getTopic(identifier)`
- **`ReleasingAirlock`** — `release(SealToken)` — for airlocks that support explicit slot release
- **`RefreshingAirlock`** — `refresh(SealToken, ?ttl): ?SealToken` — for extending leases
- **`EntryResult`** — immutable value object returned by `enter()`. Either `admitted` (has token) or `queued` (has position)

### Airlock Implementations

| Class | Strategy | Implements |
|---|---|---|
| `OpportunisticAirlock` | First-come best-effort, no queue | `Airlock`, `ReleasingAirlock` |
| `RateLimitingAirlock` | Pure rate limiting, no release | `Airlock` |
| `QueueAirlock` | Fair queuing (FIFO or Lottery) with notifications | `Airlock`, `ReleasingAirlock` |

### Seal System (capacity enforcement)

`Seal::tryAcquire(): ?SealToken` — the core locking primitive.

Sub-interfaces: `ReleasableSeal`, `RefreshableSeal`, `LockingSeal` (marker), `RateLimitingSeal` (marker), `PortableToken` (marker).

Implementations live in `Bridge/Symfony/Seal/`:
- `SymfonyLockSeal` — wraps Symfony Lock
- `SymfonySemaphoreSeal` — wraps Symfony Semaphore (N concurrent)
- `SymfonyRateLimiterSeal` — wraps Symfony RateLimiter
- `CompositeSeal` — chains a LockingSeal + RateLimitingSeal (both must pass)

### Queue System

`QueueInterface`: `add`, `remove`, `peek`, `getPosition`.

- `FifoQueue` — strict FIFO, backed by `InMemoryFifoQueueStore` or `RedisFifoQueueStore` (Lua scripts)
- `LotteryQueue` — random selection, backed by `RedisLotteryQueueStore`
- `BackpressureQueue` — decorator that blocks admission when `HealthCheckerInterface::getScore()` is below threshold

### Decorators (`Decorator/`)

- `LoggingAirlock` — wraps any `Airlock`, logs via PSR-3 `LoggerInterface`
- `EventDispatchingAirlock` — wraps any `Airlock`, dispatches PSR-14 events

Both implement `Airlock`, `ReleasingAirlock`, `RefreshingAirlock` with runtime `instanceof` guards for release/refresh.

### Events (`Event/`)

- `EntryAdmittedEvent` — user gained access
- `EntryQueuedEvent` — user was queued
- `UserLeftEvent` — user voluntarily left
- `LockReleasedEvent` — lock released
- `LeaseRefreshedEvent` — lease extended

### Notifiers

`AirlockNotifierInterface::notify(identifier, topic)` — called by `QueueAirlock::release()` to alert the next person.

- `NullAirlockNotifier` — no-op (polling-based or testing)
- `MercureAirlockNotifier` — real-time push via Symfony Mercure SSE

### Bridges

- `Bridge/Symfony/` — Seal implementations (Lock, Semaphore, RateLimiter) + Mercure hub factory
- `Bridge/Mercure/` — `MercureAirlockNotifier`
- `Bridge/Laravel/` — Service provider, facade, config

### Exceptions

- `LeaseExpiredException` — refreshing an expired token
- `SealAcquiringException` — seal acquisition failure
- `SealReleasingException` — seal release failure

## Coding Conventions

- PHP 8.4, `declare(strict_types=1)` on every file
- PSR-12 + Slevomat rules (see `phpcs.xml`): trailing commas, early returns, no Yoda, alphabetically sorted uses, `::class` over strings
- PHPStan level 8
- `final readonly class` for value objects and leaf implementations
- Interfaces for all extension points
- Composition over inheritance (CompositeSeal, BackpressureQueue, decorators)
- Tests: PHPUnit 13, `createMockForIntersectionOfInterfaces` for intersection types, `#[AllowMockObjectsWithoutExpectations]` attribute on tests with unused mocks

## Directory Layout

```
src/
  Airlock.php, EntryResult.php, ReleasingAirlock.php, RefreshingAirlock.php
  OpportunisticAirlock.php, QueueAirlock.php, RateLimitingAirlock.php
  HealthCheckerInterface.php
  Seal/          — Seal, SealToken, ReleasableSeal, RefreshableSeal, CompositeSeal, markers
  Queue/         — QueueInterface, FifoQueue, LotteryQueue, BackpressureQueue, Storage/
  Notifier/      — AirlockNotifierInterface, NullAirlockNotifier
  Decorator/     — LoggingAirlock, EventDispatchingAirlock
  Event/         — EntryAdmittedEvent, EntryQueuedEvent, UserLeftEvent, LockReleasedEvent, LeaseRefreshedEvent
  Exception/     — LeaseExpiredException, SealAcquiringException, SealReleasingException
  Bridge/
    Symfony/Seal/   — SymfonyLockSeal, SymfonySemaphoreSeal, SymfonyRateLimiterSeal + tokens
    Symfony/Mercure/ — SymfonyMercureHubFactory
    Mercure/        — MercureAirlockNotifier
    Laravel/        — ServiceProvider, Facade, config
tests/
  Unit/          — mirrors src/ structure
  Integration/   — Redis-backed tests (need Docker)
  Factory/       — RedisFactory helper
examples/
  app/           — Spiral Framework example app with multiple airlock strategies
```

## Flow: User Enters an Airlock

```
enter(identifier) →
  [OpportunisticAirlock] seal.tryAcquire() → admitted or queued(-1)
  [RateLimitingAirlock]  seal.tryAcquire() → admitted or queued(-1)
  [QueueAirlock]         queue.add() → position
                         if position == 1 → seal.tryAcquire() → admitted or queued(1)
                         if position > 1  → queued(position)

release(token) →
  [QueueAirlock] seal.release() → queue.peek() → notifier.notify(next)
```
