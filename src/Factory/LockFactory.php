<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Factory;

use Clegginabox\Airlock\Bridge\Symfony\Seal\SymfonyLockSeal;
use Clegginabox\Airlock\Seal\LocalLockSeal;
use Clegginabox\Airlock\Seal\RemoteLockSeal;
use Memcached;
use Redis;
use Symfony\Component\Lock\Bridge\DynamoDb\Store\DynamoDbStore;
use Symfony\Component\Lock\LockFactory as SymfonyLockFactory;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\DoctrineDbalPostgreSqlStore;
use Symfony\Component\Lock\Store\DoctrineDbalStore;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Lock\Store\MemcachedStore;
use Symfony\Component\Lock\Store\MongoDbStore;
use Symfony\Component\Lock\Store\PdoStore;
use Symfony\Component\Lock\Store\PostgreSqlStore;
use Symfony\Component\Lock\Store\RedisStore;
use Symfony\Component\Lock\Store\ZookeeperStore;
use Zookeeper;

class LockFactory
{
    public static function flock(?string $lockPath, string $resource, int $ttl, bool $autoRelease): LocalLockSeal
    {
        return new LocalLockSeal(
            factory: new SymfonyLockFactory(
                new FlockStore($lockPath ?? sys_get_temp_dir())
            ),
            resource: $resource,
            ttlInSeconds: $ttl,
            autoRelease: $autoRelease
        );
    }

    /**
     * @remote
     * @expiring
     */
    public static function memcached(Memcached $memcached, string $resource, int $ttl, bool $autoRelease): RemoteLockSeal
    {
        return new RemoteLockSeal(
            factory: new SymfonyLockFactory(new MemcachedStore($memcached)),
            resource: $resource,
            ttlInSeconds: $ttl,
            autoRelease: $autoRelease
        );
    }

    /**
     * @remote
     * @expiring
     */
    public static function mongodb(string $dsn, array $options, string $resource, int $ttl, bool $autoRelease): SymfonyLockSeal
    {
        return self::lockSeal(
            store: new MongoDbStore($dsn, $options),
            resource: $resource,
            ttl: $ttl,
            autoRelease: $autoRelease
        );
    }

    /**
     * @remote
     * @expiring
     */
    public static function pdo(string $dsn, array $options, string $resource, int $ttl, bool $autoRelease): SymfonyLockSeal
    {
        return self::lockSeal(
            store: new PdoStore($dsn, $options),
            resource: $resource,
            ttl: $ttl,
            autoRelease: $autoRelease
        );
    }

    public static function doctrineDbal(string $dsn, string $resource, int $ttl, bool $autoRelease): SymfonyLockSeal
    {
        return self::lockSeal(
            store: new DoctrineDbalStore($dsn),
            resource: $resource,
            ttl: $ttl,
            autoRelease: $autoRelease
        );
    }

    /**
     * @remote
     */
    public static function postgresSql(string $dsn, string $resource, int $ttl, bool $autoRelease): SymfonyLockSeal
    {
        return self::lockSeal(
            store: new PostgreSqlStore($dsn),
            resource: $resource,
            ttl: $ttl,
            autoRelease: $autoRelease
        );
    }

    public static function doctrineDbalPostgresSql(string $dsn, string $resource, int $ttl, bool $autoRelease): SymfonyLockSeal
    {
        return self::lockSeal(
            store: new DoctrineDbalPostgreSqlStore($dsn),
            resource: $resource,
            ttl: $ttl,
            autoRelease: $autoRelease
        );
    }

    /**
     * @remote
     * @expiring
     */
    public static function redis(Redis $redis, string $resource, int $ttl, bool $autoRelease): RemoteLockSeal
    {
        return new RemoteLockSeal(
            factory: new SymfonyLockFactory(new RedisStore($redis)),
            resource: $resource,
            ttlInSeconds: $ttl,
            autoRelease: $autoRelease
        );
    }

    /**
     * @remote
     */
    public static function zookeeper(Zookeeper $zookeeper, string $resource, int $ttl, bool $autoRelease): SymfonyLockSeal
    {
        return self::lockSeal(
            store: new ZookeeperStore($zookeeper),
            resource: $resource,
            ttl: $ttl,
            autoRelease: $autoRelease
        );
    }

    public static function dynamoDb(string $dsn, string $resource, int $ttl, bool $autoRelease): SymfonyLockSeal
    {
        return self::lockSeal(
            store: new DynamoDbStore($dsn),
            resource: $resource,
            ttl: $ttl,
            autoRelease: $autoRelease
        );
    }

    public static function combined()
    {
        // @todo
    }

    private static function lockSeal(PersistingStoreInterface $store, string $resource, int $ttl, bool $autoRelease): SymfonyLockSeal
    {
        return new SymfonyLockSeal(
            factory: new SymfonyLockFactory($store),
            resource: $resource,
            ttlInSeconds: $ttl,
            autoRelease: $autoRelease
        );
    }

    private static function localLockSeal(PersistingStoreInterface $store, string $resource, int $ttl, bool $autoRelease): LocalLockSeal
    {
    }
}
