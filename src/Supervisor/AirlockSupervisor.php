<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Supervisor;

use Clegginabox\Airlock\Notifier\AirlockNotifierInterface;
use Clegginabox\Airlock\Presence\PresenceProviderInterface;
use Clegginabox\Airlock\Queue\EnumerableQueue;
use Clegginabox\Airlock\Reservation\ReservationStoreInterface;

/**
 * Time-driven supervisor that handles queue promotion, candidate eviction,
 * and presence-based clean-up
 *
 * Call tick() periodically (from a CLI loop, cron job, or async timer).
 * The supervisor does NOT acquire seals — it notifies candidates, and
 * the client calls enter() on the request path to acquire a seal.
 */
final class AirlockSupervisor
{
    private ?string $lastNotifiedCandidate = null;

    private int $lastNotifiedAt = 0;

    /**
     * @var null|\Closure():bool
     */
    private readonly ?\Closure $canNotifyCandidate;

    public function __construct(
        private readonly EnumerableQueue $queue,
        private readonly AirlockNotifierInterface $notifier,
        private readonly string $topicPrefix = '/waiting-room',
        private readonly int $claimWindowSeconds = 10,
        private readonly ?PresenceProviderInterface $presenceProvider = null,
        private readonly ?ReservationStoreInterface $reservations = null,
        ?\Closure $canNotifyCandidate = null,
    ) {
        $this->canNotifyCandidate = $canNotifyCandidate;
    }

    /**
     * Perform one maintenance cycle
     *
     * 1. Evict disconnected users from the queue (if presence provider available)
     * 2. Peek at the queue to select/confirm a candidate
     * 3. Verify the candidate can actually claim now (if availability callback provided)
     * 4. Check if the current candidate has expired their claim window — evict if so
     * 5. Notify the candidate if they are new or cooldown has expired
     */
    public function tick(): SupervisorTickResult
    {
        $evicted = [];

        // Step 1: Presence-based cleanup
        if ($this->presenceProvider !== null) {
            foreach ($this->queue->all() as $identifier) {
                $topic = $this->topicFor($identifier);

                if ($this->presenceProvider->isConnected($identifier, $topic)) {
                    continue;
                }

                $this->queue->remove($identifier);
                $evicted[] = $identifier;
                $this->reservations?->clear($identifier);

                if ($identifier !== $this->lastNotifiedCandidate) {
                    continue;
                }

                $this->lastNotifiedCandidate = null;
            }
        }

        // Step 2: Peek at the queue for a candidate
        $candidate = $this->queue->peek();

        if ($candidate === null) {
            $this->lastNotifiedCandidate = null;

            return new SupervisorTickResult($evicted, null);
        }

        if ($this->canNotifyCandidate !== null && !($this->canNotifyCandidate)()) {
            return new SupervisorTickResult($evicted, null);
        }

        $now = time();
        $isNewCandidate = $candidate !== $this->lastNotifiedCandidate;
        $cooldownExpired = ($now - $this->lastNotifiedAt) >= $this->claimWindowSeconds;

        // Same candidate, still within cooldown — skip
        if (!$isNewCandidate && !$cooldownExpired) {
            return new SupervisorTickResult($evicted, null);
        }

        // Step 3: Candidate eviction — notified but never claimed
        // After the early return above, $cooldownExpired is guaranteed true here
        if (!$isNewCandidate) {
            $this->queue->remove($candidate);
            $evicted[] = $candidate;
            $this->reservations?->clear($candidate);
            $this->lastNotifiedCandidate = null;

            // Pick next candidate immediately
            $candidate = $this->queue->peek();

            if ($candidate === null) {
                return new SupervisorTickResult($evicted, null);
            }
        }

        // Step 4: Notify the (new) candidate
        $claimNonce = $this->reservations?->reserve($candidate, $this->claimWindowSeconds);
        $this->lastNotifiedCandidate = $candidate;
        $this->lastNotifiedAt = $now;
        $this->notifier->notify($candidate, $this->topicFor($candidate), $claimNonce);

        return new SupervisorTickResult($evicted, $candidate);
    }

    /**
     * Reset internal state (after a deployment or manual intervention)
     */
    public function reset(): void
    {
        $this->lastNotifiedCandidate = null;
        $this->lastNotifiedAt = 0;
    }

    private function topicFor(string $identifier): string
    {
        return rtrim($this->topicPrefix, '/') . '/' . $identifier;
    }
}
