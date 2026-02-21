<?php

declare(strict_types=1);

namespace App\Command;

use App\Examples\RedisLotteryQueue\RedisLotteryQueue;
use Clegginabox\Airlock\Bridge\Symfony\Mercure\SymfonyMercureHubFactory;
use Clegginabox\Airlock\Queue\LotteryQueue;
use Clegginabox\Airlock\Queue\Storage\Lottery\RedisLotteryQueueStore;
use Redis;
use Spiral\Console\Attribute\AsCommand;
use Spiral\Console\Command;
use Symfony\Component\Mercure\Update;

use function Amp\async;
use function Amp\delay;
use function Amp\Future\awaitAll;

#[AsCommand(
    name: 'maintain:queue:lottery',
    description: 'Maintains lottery queue — re-notifies stale candidates when slot holders abandon without releasing'
)]
class LotteryQueueMaintainCommand extends Command
{
    /** How often to check the queue (seconds). */
    private const int POLL_INTERVAL = 5;

    /** Must match the seal TTL in AirlockFactory — used as the notification cooldown. */
    private const int SEAL_TTL = 10;

    /** Must match the claim window in AirlockFactory. */
    private const int CLAIM_WINDOW = 5;

    public function __construct(
        private Redis $redis,
    ) {
        parent::__construct();
    }

    public function __invoke(): void
    {
        $this->writeln('<info>Starting lottery queue maintenance loop...</info>');

        $queue = new LotteryQueue(
            new RedisLotteryQueueStore(
                redis: $this->redis,
                setKey: RedisLotteryQueue::SET_KEY->value,
                candidateKey: RedisLotteryQueue::CANDIDATE_KEY->value,
                candidateTtlSeconds: self::CLAIM_WINDOW,
            ),
        );

        $hubUrl = getenv('MERCURE_HUB_URL') ?: 'http://localhost/.well-known/mercure';
        $jwtSecret = getenv('MERCURE_JWT_SECRET') ?: 'airlock-mercure-secret-32chars-minimum';
        $hub = SymfonyMercureHubFactory::create($hubUrl, $jwtSecret);

        $futures = [];

        $futures[] = async(function () use ($queue, $hub): void {
            $lastNotifiedCandidate = null;
            $lastNotifiedAt = 0;

            while (true) {
                delay(self::POLL_INTERVAL);

                // Ensure a candidate key exists (self-heal expired keys)
                $candidate = $queue->peek();

                if ($candidate === null) {
                    $lastNotifiedCandidate = null;

                    continue;
                }

                $now = time();
                $isNewCandidate = $candidate !== $lastNotifiedCandidate;
                $cooldownExpired = ($now - $lastNotifiedAt) >= self::SEAL_TTL;

                if (!$isNewCandidate && !$cooldownExpired) {
                    // Same candidate, still within cooldown — the normal
                    // release→notify flow or a previous notification should
                    // be handling this. Don't spam.
                    continue;
                }

                // Cooldown expired on the SAME candidate — they were notified
                // but never claimed. Kick them out and move on.
                if (!$isNewCandidate && $cooldownExpired) {
                    $this->writeln(sprintf(
                        '<comment>[maintain]</comment> Evicting <info>%s</info> — notified but never claimed',
                        $candidate,
                    ));

                    $queue->remove($candidate);
                    $lastNotifiedCandidate = null;

                    // Immediately pick the next candidate
                    $nextCandidate = $queue->peek();

                    if ($nextCandidate === null) {
                        continue;
                    }

                    $candidate = $nextCandidate;
                }

                $lastNotifiedCandidate = $candidate;
                $lastNotifiedAt = $now;

                $this->writeln(sprintf(
                    '<comment>[maintain]</comment> Notifying candidate <info>%s</info>',
                    $candidate,
                ));

                $hub->publish(
                    new Update(
                        sprintf('/waiting-room/%s', $candidate),
                        json_encode(['event' => 'your_turn'], JSON_THROW_ON_ERROR),
                    ),
                );
            }
        });

        awaitAll($futures);
    }
}
