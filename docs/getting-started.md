# Getting Started

::: warning
**Very Early Work in Progress** — This library is under active development and not yet production-ready. APIs will change, many implementations are stubs, and test coverage is incomplete. Use at your own risk, contributions welcome.
:::

## Installation

```bash
composer require clegginabox/airlock
```

Some Seal implementations require PHP extensions that are only available in certain environments:

| Extension | Required by | Notes |
|---|---|---|
| `ext-redis` | Redis-backed queues and stores | Docker-only locally |
| `ext-memcached` | Memcached lock stores | Docker-only locally |
| `ext-zookeeper` | ZooKeeper lock stores | Docker-only locally |

If installing locally without these extensions:

```bash
composer require clegginabox/airlock --ignore-platform-reqs
```

## Quick Example

Protect an endpoint from the hug of death — cap concurrent access, reject the rest:

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

if ($result->isAdmitted()) {
    // They're in. Do the expensive thing.
} else {
    // Full. Show a "please wait" page, return 503, etc.
}
```

## What Just Happened?

1. A **Seal** was created — a semaphore allowing 20 concurrent users, backed by Redis.
2. An **OpportunisticAirlock** was wired up — first come, best effort, no queue.
3. `enter()` tried to acquire a slot. If capacity was free, the user got in. If not, they didn't.

That's the whole pattern. Every Airlock works the same way — you just swap the pieces.

## Next Steps

- [Core Concepts](/core-concepts) — understand the Seal + Strategy + Notifier model
- [Strategies](/strategies) — pick the right admission strategy for your use case
- [Recipes](/recipes) — real-world examples you can drop straight in
