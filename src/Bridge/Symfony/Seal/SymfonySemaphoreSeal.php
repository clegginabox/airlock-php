<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Bridge\Symfony\Seal;

use Clegginabox\Airlock\Exception\LeaseExpiredException;
use Clegginabox\Airlock\Exception\SealReleasingException;
use Clegginabox\Airlock\Seal\RefreshableSeal;
use Clegginabox\Airlock\Seal\ReleasableSeal;
use Clegginabox\Airlock\Seal\SealToken;
use Symfony\Component\Semaphore\Exception\SemaphoreExpiredException;
use Symfony\Component\Semaphore\Exception\SemaphoreReleasingException;
use Symfony\Component\Semaphore\Key;
use Symfony\Component\Semaphore\SemaphoreFactory;
use Symfony\Component\Semaphore\SemaphoreInterface;

final readonly class SymfonySemaphoreSeal implements ReleasableSeal, RefreshableSeal
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

    public function tryAcquire(): ?SymfonySemaphoreToken
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

        return new SymfonySemaphoreToken($key);
    }

    public function release(SealToken $token): void
    {
        if (!$token instanceof SymfonySemaphoreToken) {
            throw new SealReleasingException(
                sprintf('Invalid token type: %s. Expected %s', $token::class, SymfonySemaphoreToken::class)
            );
        }

        $semaphore = $this->factory->createSemaphoreFromKey($token->getKey());

        try {
            $semaphore->release();
        } catch (SemaphoreReleasingException $e) {
            throw new SealReleasingException(
                sprintf('Unable to release semaphore: %s', $e->getMessage()),
                $e->getCode(),
                $e
            );
        }
    }

    public function refresh(SealToken $token, ?float $ttlInSeconds = null): SymfonySemaphoreToken
    {
        if (!$token instanceof SymfonySemaphoreToken) {
            throw new LeaseExpiredException((string) $token, 'Invalid token type');
        }

        $semaphore = $this->factory->createSemaphoreFromKey(
            $token->getKey(),
            $this->ttlInSeconds,
            $this->autoRelease
        );

        try {
            $effectiveTtl = $ttlInSeconds ?? $this->ttlInSeconds;
            $semaphore->refresh($effectiveTtl);

            return new SymfonySemaphoreToken($token->getKey());
        } catch (SemaphoreExpiredException $e) {
            throw new LeaseExpiredException((string) $token, $e->getMessage());
        }
    }

    public function isExpired(SymfonySemaphoreToken $token): bool
    {
        return $this->resolveSemaphore($token)?->isExpired() ?? true;
    }

    public function isAcquired(SymfonySemaphoreToken $token): bool
    {
        return $this->resolveSemaphore($token)?->isAcquired() ?? false;
    }

    public function getRemainingLifetime(SymfonySemaphoreToken $token): ?float
    {
        return $this->resolveSemaphore($token)?->getRemainingLifetime();
    }

    private function resolveSemaphore(SymfonySemaphoreToken $token): ?SemaphoreInterface
    {
        return $this->factory->createSemaphoreFromKey(
            $token->getKey(),
            $this->ttlInSeconds,
            $this->autoRelease
        );
    }

    public function __toString(): string
    {
        return $this->resource;
    }
}
