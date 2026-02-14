<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Factory;

use Clegginabox\Airlock\Airlock;
use Clegginabox\Airlock\Bridge\Symfony\Seal\SymfonyLockSeal;
use Clegginabox\Airlock\Bridge\Symfony\Seal\SymfonySemaphoreSeal;
use Clegginabox\Airlock\Notifier\NullAirlockNotifier;
use Clegginabox\Airlock\OpportunisticAirlock;
use Clegginabox\Airlock\QueueAirlock;
use Clegginabox\Airlock\Seal\ReleasableSeal;
use Clegginabox\Airlock\Seal\Seal;
use InvalidArgumentException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\StoreFactory as LockStoreFactory;
use Symfony\Component\Semaphore\SemaphoreFactory;
use Symfony\Component\Semaphore\Store\StoreFactory as SemaphoreStoreFactory;

/**
 * Architectural sketch of the "store + limit + strategy" assembly flow.
 *
 * Intent:
 * - Keep broad store compatibility by defaulting limit=1 to Symfony Lock stores.
 * - Use Symfony Semaphore only when the backend can actually support it.
 * - Fail fast for unsupported limit > 1 combinations rather than silently
 *   downgrading behavior.
 *
 * This is deliberately conservative: it shows the structure and policy points,
 * not every possible implementation variant.
 */
final class SymfonyAirlockFactory
{
    public function create(SymfonyAirlockFactoryOptions $options): Airlock
    {
        $seal = $this->createSeal($options);

        // No queue configured => anti-hug / first-wins mode.
        if (!$options->usesQueue()) {
            return new OpportunisticAirlock($seal);
        }

        // Queue mode needs explicit release semantics to wake up the next user.
        if (!$seal instanceof ReleasableSeal) {
            throw new InvalidArgumentException(
                sprintf(
                    'Queue mode requires a releasable seal, got %s.',
                    $seal::class
                )
            );
        }

        /** @var Seal&ReleasableSeal $queueSeal */
        $queueSeal = $seal;

        return new QueueAirlock(
            $queueSeal,
            $options->queue ?? throw new InvalidArgumentException('Queue mode requires a queue.'),
            $options->notifier ?? new NullAirlockNotifier(),
            $options->topicPrefix,
        );
    }

    private function createSeal(SymfonyAirlockFactoryOptions $options): Seal
    {
        // Policy: limit=1 gets maximal backend support via Symfony Lock stores.
        if ($options->limit === 1) {
            $store = LockStoreFactory::createStore($options->storeConnection);
            $factory = new LockFactory($store);

            return new SymfonyLockSeal(
                $factory,
                resource: $options->resource,
                ttlInSeconds: $options->ttlInSeconds ?? 300.0,
                autoRelease: $options->autoRelease,
            );
        }

        // Policy: limit>1 uses Symfony Semaphore, which currently has narrower
        // backend support than Symfony Lock.
        if (!$this->supportsSemaphoreBackend($options->storeConnection)) {
            throw new InvalidArgumentException(
                'limit > 1 currently requires a Redis-style backend for Symfony Semaphore.'
            );
        }

        $store = SemaphoreStoreFactory::createStore($options->storeConnection);
        $factory = new SemaphoreFactory($store);

        return new SymfonySemaphoreSeal(
            $factory,
            resource: $options->resource,
            limit: $options->limit,
            ttlInSeconds: $options->ttlInSeconds,
            autoRelease: $options->autoRelease,
        );
    }

    /**
     * Mirrors the practical backend support of Symfony Semaphore StoreFactory.
     *
     * Kept as an explicit gate so the decision is obvious to library users.
     */
    private function supportsSemaphoreBackend(object|string $connection): bool
    {
        if (is_object($connection)) {
            if (
                $connection instanceof \Redis
                || $connection instanceof \RedisArray
                || $connection instanceof \RedisCluster
            ) {
                return true;
            }

            if (class_exists(\Relay\Relay::class) && $connection instanceof \Relay\Relay) {
                return true;
            }

            return
                interface_exists(\Predis\ClientInterface::class)
                && $connection instanceof \Predis\ClientInterface
            ;
        }

        return str_starts_with($connection, 'redis:')
            || str_starts_with($connection, 'rediss:')
            || str_starts_with($connection, 'valkey:')
            || str_starts_with($connection, 'valkeys:');
    }
}
