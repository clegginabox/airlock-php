# Decorators & Events

Airlock uses composition for cross-cutting concerns. Wrap any airlock with a decorator to add logging, event dispatching, or both.

## LoggingAirlock

Wraps any `Airlock` and logs via PSR-3 `LoggerInterface`.

```php
use Clegginabox\Airlock\Decorator\LoggingAirlock;

$airlock = new LoggingAirlock(
    inner: $queueAirlock,
    logger: $psrLogger,
);
```

**Implements:** `Airlock`, `ReleasingAirlock`, `RefreshingAirlock`

Logs:
- `debug` — every `enter()` attempt
- `info` — admission, queuing, leaving, releasing, refreshing

If the inner airlock doesn't implement `ReleasingAirlock` or `RefreshingAirlock`, calling those methods throws a `LogicException` at runtime. No need to wire up different decorators for different airlocks.

## EventDispatchingAirlock

Wraps any `Airlock` and dispatches PSR-14 events.

```php
use Clegginabox\Airlock\Decorator\EventDispatchingAirlock;

$airlock = new EventDispatchingAirlock(
    inner: $queueAirlock,
    dispatcher: $psrEventDispatcher,
    airlockIdentifier: 'ticket-sales',
);
```

**Implements:** `Airlock`, `ReleasingAirlock`, `RefreshingAirlock`

| Parameter | Type | Default | Description |
|---|---|---|---|
| `inner` | `Airlock` | — | The wrapped airlock |
| `dispatcher` | `EventDispatcherInterface` | — | PSR-14 event dispatcher |
| `airlockIdentifier` | `string` | `'default'` | Identifies which airlock fired the event |

Same runtime `instanceof` guard as `LoggingAirlock` for `release()` and `refresh()`.

## Stacking Decorators

Decorators compose. Wrap them in whatever order makes sense:

```php
$airlock = new LoggingAirlock(
    inner: new EventDispatchingAirlock(
        inner: $queueAirlock,
        dispatcher: $dispatcher,
    ),
    logger: $logger,
);
```

## Events

All events are `final readonly` classes with public constructor-promoted properties. No getters, no setters — just data.

### EntryAdmittedEvent

Dispatched when a user is admitted.

```php
final readonly class EntryAdmittedEvent
{
    public function __construct(
        public string $airlock,
        public string $identifier,
        public SealToken $token,
        public string $topic,
    ) {}
}
```

### EntryQueuedEvent

Dispatched when a user is queued.

```php
final readonly class EntryQueuedEvent
{
    public function __construct(
        public string $airlock,
        public string $identifier,
        public int $position,
        public string $topic,
    ) {}
}
```

### UserLeftEvent

Dispatched when a user voluntarily leaves.

```php
final readonly class UserLeftEvent
{
    public function __construct(
        public string $airlock,
        public string $identifier,
    ) {}
}
```

### LockReleasedEvent

Dispatched when a lock is explicitly released.

```php
final readonly class LockReleasedEvent
{
    public function __construct(
        public string $airlock,
        public SealToken $token,
    ) {}
}
```

### LeaseRefreshedEvent

Dispatched when a lease is extended.

```php
final readonly class LeaseRefreshedEvent
{
    public function __construct(
        public string $airlock,
        public SealToken $oldToken,
        public ?SealToken $newToken,
        public ?float $ttlInSeconds,
    ) {}
}
```
