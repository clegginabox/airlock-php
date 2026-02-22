# Bridges

Bridges integrate Airlock with specific frameworks and libraries. They live under `src/Bridge/` and provide the concrete implementations that make everything work.

## Symfony

The Symfony bridge provides all the current Seal implementations.

### Lock

`SymfonyLockSeal` — mutex (exactly one holder). See [Seals Reference](/reference/seals#symfonylockSeal).

Requires: `symfony/lock`

### Semaphore

`SymfonySemaphoreSeal` — N concurrent holders. See [Seals Reference](/reference/seals#symfonysemaphoreseal).

Requires: `symfony/semaphore`

### Rate Limiter

`SymfonyRateLimiterSeal` — X per time window. See [Seals Reference](/reference/seals#symfonyratelimiterseal).

Requires: `symfony/rate-limiter`

### Mercure

Real-time push notifications via Server-Sent Events.

`SymfonyMercureHubFactory` creates Mercure hub instances for use with the `MercureAirlockNotifier`.

Requires: `symfony/mercure`

## Mercure

`MercureAirlockNotifier` — the "your table is ready" buzzer. Pushes real-time notifications to waiting users via Mercure SSE.

```php
use Clegginabox\Airlock\Bridge\Mercure\MercureAirlockNotifier;

$notifier = new MercureAirlockNotifier($mercureHub);
```

Implements `AirlockNotifierInterface`. Called by `QueueAirlock::release()` to alert the next person in line.

For polling-based systems or testing, use `NullAirlockNotifier` instead.

## Laravel

::: info
The Laravel bridge is under active development. Service provider, facade, and config scaffolding exist but are not yet complete.
:::

Planned integration:

- **Service Provider** — auto-registers Airlock bindings
- **Facade** — `Airlock::withAdmitted(...)` shorthand
- **Config** — `config/airlock.php` for strategy, limits, and backend configuration

```php
// Planned usage
use Clegginabox\Airlock\Bridge\Laravel\Facade\Airlock;

$key = "pay-now:{$user->id}:{$order->id}";

return Airlock::withAdmitted($key, function () use ($order) {
    $this->payments->charge($order);
    return response()->json(['ok' => true]);
}, timeoutSeconds: 2);
```
