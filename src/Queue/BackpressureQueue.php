<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Queue;

use Clegginabox\Airlock\HealthCheckerInterface;

/**
 * BackpressureQueue - Adaptive admission based on downstream health.
 *
 * THE CONCEPT:
 * ------------
 * Normal queues admit at a fixed rate. Backpressure queues adapt based on
 * signals from downstream systems. If your database is slow, admit fewer
 * people. If it recovers, speed up again.
 *
 * Think of it like a tap that adjusts water flow based on how fast the sink
 * is draining. If the sink is backing up, reduce the flow.
 *
 * CORE IDEA:
 * ----------
 * This is a DECORATOR around another queue. It doesn't replace FIFO/Lottery
 * logic - it wraps it and adds a "should we even try right now?" check.
 *
 *   BackpressureQueue
 *       └── wraps RedisFifoQueue (or any other queue)
 *
 * HEALTH SIGNALS - where does backpressure info come from?
 * --------------------------------------------------------
 * Option A: External health checker (injected dependency)
 *   - A service that polls your DB, API, etc. and returns a 0.0-1.0 health score
 *   - Queue asks "what's the health?" before each peek/pop
 *
 * Option B: Redis key convention
 *   - Something else writes to a Redis key like "backpressure:health" (0.0-1.0)
 *   - Queue reads that key directly
 *   - Simple, decoupled - a cron job or the app itself updates the key
 *
 * Option C: Self-measuring (advanced)
 *   - Track how long admitted users take to release()
 *   - If latency spikes, reduce admission rate
 *   - Requires feedback loop from the Seal back to the Queue
 *
 * WHAT DOES "BACKPRESSURE" ACTUALLY CHANGE?
 * -----------------------------------------
 * When health is low, you have choices:
 *
 * 1. THROTTLE PEEK/POP
 *    - peek() returns null even if queue has people
 *    - "Sorry, system is busy, try again shortly"
 *    - Simple but blunt
 *
 * 2. PROBABILISTIC ADMISSION
 *    - health = 0.3 means 30% chance peek() returns the real head
 *    - Randomly skip turns based on health score
 *    - Smoother degradation
 *
 * 3. DELAYED ADMISSION
 *    - peek() returns head only if enough time has passed since last admission
 *    - Low health = longer delays between admissions
 *    - Rate limiting based on health
 *
 *
 * HEALTH CHECKER INTERFACE (you'll need this):
 * --------------------------------------------
 *
 *   interface HealthCheckerInterface
 *   {
 *       /** @return float 0.0 (dead) to 1.0 (fully healthy)
 *       public function getScore(): float;
 *   }
 *
 *   // Simple Redis-based implementation:
 *   class RedisHealthChecker implements HealthCheckerInterface
 *   {
 *       public function __construct(private Redis $redis, private string $key) {}
 *
 *       public function getScore(): float
 *       {
 *           return (float) ($this->redis->get($this->key) ?? 1.0);
 *       }
 *   }
 *
 * WHO SETS THE HEALTH SCORE?
 * --------------------------
 * That's outside this class. Could be:
 * - A middleware that tracks response times and writes to Redis
 * - A cron job that pings your DB and updates the key
 * - Your app's exception handler (errors spike = reduce health)
 * - An external monitoring tool via webhook
 *
 * QUESTIONS TO CONSIDER:
 * ----------------------
 * 1. Should getPosition() reflect backpressure? (Probably not - your position
 *    in line doesn't change, just how fast the line moves)
 *
 * 2. What about pop()? Should it also respect backpressure or just peek()?
 *    (Usually just peek - once you're peeked/selected, you should be admitted)
 *
 * 3. Should there be a "circuit breaker" mode where health < X completely
 *    stops admission vs gradual degradation?
 *
 * 4. How do you prevent oscillation? (Health drops -> fewer users -> health
 *    recovers -> flood of users -> health drops again)
 *    Consider: smoothing/averaging, hysteresis, ramp-up delays
 */
class BackpressureQueue implements QueueInterface
{
    public function __construct(
        private QueueInterface $inner,
        private HealthCheckerInterface $healthChecker,
        private float $minHealthToAdmit = 0.2,
    ) {}

    // Your implementation here
    public function add(string $identifier, int $priority = 0): int
    {
        return $this->inner->add($identifier, $priority);
    }

    public function remove(string $identifier): void
    {
        $this->inner->remove($identifier);
    }

    public function peek(): ?string
    {
        $score = $this->healthChecker->getScore();

        if ($score < $this->minHealthToAdmit) {
            return null;
        }

        return $this->inner->peek();
    }

    public function getPosition(string $identifier): ?int
    {
        return $this->inner->getPosition($identifier);
    }
}
