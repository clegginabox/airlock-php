# Seals

A Seal is the capacity enforcement primitive. It's the velvet rope — it decides whether there's room for one more.

## The Interface

```php
interface Seal
{
    public function tryAcquire(): ?SealToken;
}
```

`tryAcquire()` is non-blocking. It either returns a `SealToken` (you're in) or `null` (you're not). No waiting, no retries — that's the airlock's job.

## SealToken

Proof of admission. Returned by `tryAcquire()` when capacity is available.

```php
interface SealToken extends Stringable
{
    public function getResource(): string;
    public function getId(): string;
}
```

Some tokens implement `PortableToken` — meaning they're safe to serialize and pass between processes or requests (cookies, headers, job payloads).

## Sub-Interfaces

### ReleasableSeal

The slot can be given back early, before the TTL expires.

```php
interface ReleasableSeal
{
    public function release(SealToken $token): void;
}
```

### RefreshableSeal

The lease can be extended.

```php
interface RefreshableSeal
{
    /**
     * @throws LeaseExpiredException if the token is no longer valid
     */
    public function refresh(SealToken $token, ?float $ttlInSeconds = null): SealToken;
}
```

### Marker Interfaces

- `LockingSeal` — marker for locking seals (Lock, Semaphore). Used by `CompositeSeal` to distinguish between parts.
- `RateLimitingSeal` — marker for rate limiting seals. Same purpose.

## Implementations

All seal implementations live in `Bridge/Symfony/Seal/`.

### SymfonyLockSeal

Wraps Symfony Lock. Mutex — exactly one holder at a time.

```php
use Clegginabox\Airlock\Bridge\Symfony\Seal\SymfonyLockSeal;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\RedisStore;

$seal = new SymfonyLockSeal(
    factory: new LockFactory(new RedisStore($redis)),
    resource: 'my-resource',
    ttlInSeconds: 300.0,
    autoRelease: false,
);
```

**Implements:** `LockingSeal`, `ReleasableSeal`, `RefreshableSeal`

| Parameter | Type | Default | Description |
|---|---|---|---|
| `factory` | `LockFactory` | — | Symfony Lock factory |
| `resource` | `string` | `'waiting-room'` | Lock resource name |
| `ttlInSeconds` | `float` | `300.0` | Lock TTL (safety net) |
| `autoRelease` | `bool` | `false` | Release on destructor |

Additional methods: `isExpired()`, `isAcquired()`, `getRemainingLifetime()`.

### SymfonySemaphoreSeal

Wraps Symfony Semaphore. N concurrent holders.

```php
use Clegginabox\Airlock\Bridge\Symfony\Seal\SymfonySemaphoreSeal;
use Symfony\Component\Semaphore\SemaphoreFactory;
use Symfony\Component\Semaphore\Store\RedisStore;

$seal = new SymfonySemaphoreSeal(
    factory: new SemaphoreFactory(new RedisStore($redis)),
    resource: 'site_capacity',
    limit: 20,
    weight: 1,
    ttlInSeconds: 300.0,
    autoRelease: false,
);
```

**Implements:** `LockingSeal`, `ReleasableSeal`, `RefreshableSeal`

| Parameter | Type | Default | Description |
|---|---|---|---|
| `factory` | `SemaphoreFactory` | — | Symfony Semaphore factory |
| `resource` | `string` | `'waiting-room'` | Semaphore resource name |
| `limit` | `int` | `1` | Max concurrent holders |
| `weight` | `int` | `1` | Weight per acquisition |
| `ttlInSeconds` | `?float` | `300.0` | Lease TTL |
| `autoRelease` | `bool` | `false` | Release on destructor |

Additional methods: `isExpired()`, `isAcquired()`, `getRemainingLifetime()`.

### SymfonyRateLimiterSeal

Wraps Symfony RateLimiter. X per time window, no release.

```php
use Clegginabox\Airlock\Bridge\Symfony\Seal\SymfonyRateLimiterSeal;

$seal = new SymfonyRateLimiterSeal(
    limiter: $limiter, // Symfony LimiterInterface
    resource: 'api-calls',
);
```

**Implements:** `RateLimitingSeal`

Not releasable, not refreshable. The window is the policy.

### CompositeSeal

Chains a `LockingSeal` + any other `Seal`. Both must pass for admission.

```php
use Clegginabox\Airlock\Seal\CompositeSeal;

$seal = new CompositeSeal(
    lockingSeal: $semaphoreSeal,    // Seal & ReleasableSeal
    rateLimitingSeal: $rateLimiter, // Seal
);
```

**Implements:** `Seal`, `ReleasableSeal`

Acquires the locking seal first. If the rate limiter then rejects, the lock is released automatically. On `release()`, only the locking seal is released (rate limiting seals don't have a release concept).

## Exceptions

- `SealAcquiringException` — something went wrong trying to acquire (infrastructure failure, not "capacity full")
- `SealReleasingException` — something went wrong releasing (wrong token type, already released)
- `LeaseExpiredException` — tried to refresh a token that's no longer valid
