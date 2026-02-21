<?php

declare(strict_types=1);

namespace App\Endpoint\Console;

use App\Examples\RedisLotteryQueue\RedisLotteryQueueService;
use Clegginabox\Airlock\Seal\SealToken;
use Spiral\Console\Attribute\AsCommand;
use Spiral\Console\Attribute\Option;
use Spiral\Console\Command;
use Symfony\Component\Console\Input\InputOption;

use function Amp\async;
use function Amp\delay;
use function Amp\Future\awaitAll;

#[AsCommand(
    name: 'bots:lottery',
    description: 'Run simulated bot clients against the Redis lottery queue example.',
)]
final class BotsLotteryQueueCommand extends Command
{
    #[Option(
        name: 'bots',
        shortcut: 'b',
        description: 'Number of concurrent bots to run.',
        mode: InputOption::VALUE_OPTIONAL,
    )]
    private int $bots = 20;

    #[Option(
        name: 'rounds',
        shortcut: 'r',
        description: 'Number of successful admissions each bot should complete.',
        mode: InputOption::VALUE_OPTIONAL,
    )]
    private int $rounds = 1;

    #[Option(
        name: 'poll-ms',
        shortcut: 'p',
        description: 'Poll interval in milliseconds while waiting in queue.',
        mode: InputOption::VALUE_OPTIONAL,
    )]
    private int $pollMs = 300;

    #[Option(
        name: 'hold-ms',
        shortcut: 'd',
        description: 'How long to hold the slot in milliseconds once admitted.',
        mode: InputOption::VALUE_OPTIONAL,
    )]
    private int $holdMs = 1500;

    #[Option(
        name: 'spawn-jitter-ms',
        shortcut: 'j',
        description: 'Spawn jitter in milliseconds to avoid all bots joining at once.',
        mode: InputOption::VALUE_OPTIONAL,
    )]
    private int $spawnJitterMs = 600;

    #[Option(
        name: 'max-wait-seconds',
        shortcut: 'm',
        description: 'Max wait time per round in seconds before bot leaves and counts a timeout.',
        mode: InputOption::VALUE_OPTIONAL,
    )]
    private int $maxWaitSeconds = 45;

    public function __invoke(RedisLotteryQueueService $service): int
    {
        $bots = max(1, $this->bots);
        $rounds = max(1, $this->rounds);
        $pollMs = max(25, $this->pollMs);
        $holdMs = max(0, $this->holdMs);
        $spawnJitterMs = max(0, $this->spawnJitterMs);
        $maxWaitSeconds = max(1, $this->maxWaitSeconds);

        $this->info(sprintf(
            'Starting %d bot(s), %d round(s) each (poll=%dms hold=%dms maxWait=%ds jitter=%dms).',
            $bots,
            $rounds,
            $pollMs,
            $holdMs,
            $maxWaitSeconds,
            $spawnJitterMs,
        ));

        $futures = [];

        for ($i = 1; $i <= $bots; $i++) {
            $botId = sprintf('bot-%03d-%s', $i, bin2hex(random_bytes(3)));

            $futures[$botId] = async(function () use (
                $service,
                $botId,
                $rounds,
                $pollMs,
                $holdMs,
                $spawnJitterMs,
                $maxWaitSeconds,
            ): array {
                if ($spawnJitterMs > 0) {
                    delay($this->msToSeconds(random_int(0, $spawnJitterMs)));
                }

                $admitted = 0;
                $queuedPolls = 0;
                $timeouts = 0;

                for ($round = 0; $round < $rounds; $round++) {
                    $startedAt = microtime(true);

                    while (true) {
                        $result = $service->start($botId);

                        if ($result->isAdmitted()) {
                            $token = $result->getToken();

                            if (!$token instanceof SealToken) {
                                throw new \RuntimeException(sprintf(
                                    'Bot %s was admitted without a releasable token.',
                                    $botId,
                                ));
                            }

                            try {
                                if ($holdMs > 0) {
                                    delay($this->msToSeconds($holdMs));
                                }
                            } finally {
                                $service->releaseToken($token);
                            }

                            $admitted++;
                            break;
                        }

                        $queuedPolls++;

                        if ((microtime(true) - $startedAt) >= $maxWaitSeconds) {
                            $timeouts++;
                            $service->leave($botId);
                            break;
                        }

                        delay($this->msToSeconds($pollMs));
                    }
                }

                // Cleanup for bots that timed out during the final round.
                $service->leave($botId);

                return [
                    'admitted' => $admitted,
                    'queuedPolls' => $queuedPolls,
                    'timeouts' => $timeouts,
                ];
            });
        }

        [$errors, $results] = awaitAll($futures);

        $totalAdmitted = 0;
        $totalQueuedPolls = 0;
        $totalTimeouts = 0;

        foreach ($results as $botResult) {
            $totalAdmitted += $botResult['admitted'];
            $totalQueuedPolls += $botResult['queuedPolls'];
            $totalTimeouts += $botResult['timeouts'];
        }

        foreach ($errors as $botId => $error) {
            $this->error(sprintf('%s failed: %s', $botId, $error->getMessage()));
        }

        $attempted = $bots * $rounds;

        $this->info(sprintf(
            'Completed. attempted=%d admitted=%d queuedPolls=%d timeouts=%d errors=%d',
            $attempted,
            $totalAdmitted,
            $totalQueuedPolls,
            $totalTimeouts,
            count($errors),
        ));

        if ($totalTimeouts > 0) {
            $this->warning(sprintf(
                '%d round(s) timed out before admission. Increase --max-wait-seconds, lower --bots, or lower --hold-ms.',
                $totalTimeouts,
            ));
        }

        return $errors === [] ? self::SUCCESS : self::FAILURE;
    }

    private function msToSeconds(int $milliseconds): float
    {
        return $milliseconds / 1000;
    }
}
