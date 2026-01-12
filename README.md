# Airlock

Library + optional Symfony bundle to coordinate access to limited resources using a queue, a seal, and an optional notifier.

## Symfony bundle

Register the bundle (if not using Flex):

```php
// config/bundles.php
return [
    Clegginabox\Airlock\Bridge\Symfony\AirlockBundle::class => ['all' => true],
];
```

Example config for a single airlock:

```yaml
# config/packages/airlock.yaml
airlock:
  service_id: waiting_room.workflow
  alias: waiting_room.workflow
  topic_prefix: /workflow_waiting_room
  seal:
    type: semaphore
    semaphore:
      factory: semaphore.workflow.factory
      resource: workflow_waiting_room
      limit: '%env(int:WORKFLOW_SEMAPHORE_LIMIT)%'
      ttl_in_seconds: '%env(int:WORKFLOW_SEMAPHORE_TTL)%'
  queue:
    type: redis_fifo
    redis_fifo:
      redis: redis
  notifier:
    type: mercure
    mercure:
      hub: mercure.hub.default
```

When only one airlock is configured, the bundle exposes interface aliases:

- `Clegginabox\Airlock\AirlockInterface`
- `Clegginabox\Airlock\Seal\SealInterface`
- `Clegginabox\Airlock\Queue\QueueInterface`
- `Clegginabox\Airlock\Notifier\AirlockNotifierInterface`

## Multiple airlocks

Define named airlocks under `airlocks:`. Each airlock creates:

- `airlock.<name>` (or `service_id` if set)
- `airlock.<name>.seal`
- `airlock.<name>.queue`
- `airlock.<name>.notifier`

```yaml
# config/packages/airlock.yaml
airlock:
  airlocks:
    workflow:
      topic_prefix: /workflow_waiting_room
      seal:
        semaphore:
          factory: semaphore.workflow.factory
          resource: workflow_waiting_room
          limit: '%env(int:WORKFLOW_SEMAPHORE_LIMIT)%'
          ttl_in_seconds: '%env(int:WORKFLOW_SEMAPHORE_TTL)%'
      queue:
        type: redis_fifo
        redis_fifo:
          redis: redis
      notifier:
        type: mercure
        mercure:
          hub: mercure.hub.default

    onboarding:
      topic_prefix: /onboarding_waiting_room
      queue:
        type: redis_lottery
      notifier:
        type: null
```

When multiple airlocks are configured, interface aliases are not set to avoid ambiguity.
