<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Seal;

use Clegginabox\Airlock\Exception\LeaseExpiredException;
use Symfony\Component\Semaphore\Exception\SemaphoreExpiredException;
use Symfony\Component\Semaphore\Key;
use Symfony\Component\Semaphore\SemaphoreFactory;
use Symfony\Component\Semaphore\SemaphoreInterface;

final readonly class SemaphoreSeal implements SealInterface
{
    public function __construct(
        private SemaphoreFactory $factory,
        private string $resource = 'waiting-room',
        private int $limit = 1,
        private int $weight = 1,
        private ?float $ttlInSeconds = 300.0,
        private bool $autoRelease = false,
    ) {
    }

    public function tryAcquire(): ?string
    {
        $key = new Key($this->resource, $this->limit, $this->weight);
        $semaphore = $this->factory->createSemaphoreFromKey(
            $key,
            $this->ttlInSeconds,
            $this->autoRelease,
        );

        if (!$semaphore->acquire()) {
            return null;
        }

        return serialize($key);
    }

    public function release(string $token): void
    {
        $key = unserialize($token, ['allowed_classes' => [Key::class]]);

        if (!$key instanceof Key) {
            return;
        }

        $semaphore = $this->factory->createSemaphoreFromKey($key);
        $semaphore->release();
    }

    public function refresh(string $token, ?float $ttlInSeconds = null): string
    {
        $key = unserialize($token, ['allowed_classes' => [Key::class]]);

        if (!$key instanceof Key) {
            throw new LeaseExpiredException($token, 'Invalid token structure');
        }

        $semaphore = $this->factory->createSemaphoreFromKey($key, $this->ttlInSeconds, $this->autoRelease);

        try {
            $effectiveTtl = $ttlInSeconds ?? $this->ttlInSeconds;
            $semaphore->refresh($effectiveTtl);

            return serialize($key);
        } catch (SemaphoreExpiredException $semaphoreExpiredException) {
            throw new LeaseExpiredException($token, $semaphoreExpiredException->getMessage());
        }
    }

    public function isExpired(string $token): bool
    {
        return $this->resolveSemaphore($token)?->isExpired() ?? true;
    }

    public function isAcquired(string $token): bool
    {
        return $this->resolveSemaphore($token)?->isAcquired() ?? false;
    }

    public function getRemainingLifetime(string $token): ?float
    {
        return $this->resolveSemaphore($token)?->getRemainingLifetime();
    }

    private function resolveSemaphore(string $token): ?SemaphoreInterface
    {
        $key = unserialize($token, ['allowed_classes' => [Key::class]]);

        if (!$key instanceof Key) {
            return null;
        }

        return $this->factory
            ->createSemaphoreFromKey($key, $this->ttlInSeconds, $this->autoRelease);
    }

    public function __toString(): string
    {
        return $this->resource;
    }
}
