# Queues

When capacity is full, users wait in a queue. The queue decides *who* gets the next available slot.

## The Interface

```php
interface QueueInterface
{
    public function add(string $identifier, int $priority = 0): int;
    public function remove(string $identifier): void;
    public function peek(): ?string;
    public function getPosition(string $identifier): ?int;
}
```

- `add()` — join the queue. Returns the position (1 = front).
- `remove()` — leave the queue (voluntarily or after admission).
- `peek()` — who's at the front? Returns the identifier, or `null` if empty.
- `getPosition()` — where am I? Returns `null` if not in the queue.

## Implementations

### FifoQueue

Strict first-in, first-out. The proper British queue.

```php
use Clegginabox\Airlock\Queue\FifoQueue;
use Clegginabox\Airlock\Queue\Storage\Fifo\InMemoryFifoQueueStore;
use Clegginabox\Airlock\Queue\Storage\Fifo\RedisFifoQueueStore;

// In-memory (testing, single-process)
$queue = new FifoQueue(new InMemoryFifoQueueStore());

// Redis-backed (production, distributed)
$queue = new FifoQueue(new RedisFifoQueueStore($redis, 'my-queue'));
```

**Trade-offs:**
- Perfectly fair — no one jumps the line
- Brittle under disconnects — dead heads stall the queue unless explicitly removed
- The `RedisFifoQueueStore` uses Lua scripts for atomic operations

### LotteryQueue

Random selection. No ordering at all.

```php
use Clegginabox\Airlock\Queue\LotteryQueue;
use Clegginabox\Airlock\Queue\Storage\Lottery\RedisLotteryQueueStore;

$queue = new LotteryQueue(new RedisLotteryQueueStore($redis, 'my-queue'));
```

**Trade-offs:**
- High throughput, self-healing under disconnects
- Not fair — someone who just joined could be picked before someone who's been waiting ages
- Great when you need liveness over fairness

### BackpressureQueue

A decorator that wraps any queue. Blocks admission entirely when system health drops below a threshold.

```php
use Clegginabox\Airlock\Queue\BackpressureQueue;

$queue = new BackpressureQueue(
    inner: $fifoQueue,
    healthChecker: $healthChecker,
    minHealthToAdmit: 0.2,
);
```

| Parameter | Type | Default | Description |
|---|---|---|---|
| `inner` | `QueueInterface` | — | The wrapped queue |
| `healthChecker` | `HealthCheckerInterface` | — | Provides a health score |
| `minHealthToAdmit` | `float` | `0.2` | Minimum score to allow `peek()` to return results |

`peek()` returns `null` if the health score is below the threshold — effectively pausing admission while the system recovers. `add()`, `remove()`, and `getPosition()` pass straight through.

## Storage Backends

### InMemoryFifoQueueStore

Array-backed. Suitable for testing and single-process use cases only.

### RedisFifoQueueStore

Redis-backed with Lua scripts for atomicity. Production-grade, distributed.

### RedisLotteryQueueStore

Redis-backed random selection. Uses Redis sorted sets.

## HealthCheckerInterface

Used by `BackpressureQueue` to decide whether the system is healthy enough to admit users.

```php
interface HealthCheckerInterface
{
    /**
     * @return float 0.0 (dead) to 1.0 (fully healthy)
     */
    public function getScore(): float;
}
```

Implement this with whatever health signal makes sense — database connection pool usage, memory pressure, response latency, external dependency status.
