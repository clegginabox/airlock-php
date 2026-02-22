<?php

declare(strict_types=1);

namespace App\Command;

use App\Factory\AirlockFactory;
use Clegginabox\Airlock\Bridge\Mercure\MercureAirlockNotifier;
use DateTimeImmutable;
use Spiral\Console\Attribute\AsCommand;
use Spiral\Console\Command;
use Symfony\Component\Mercure\HubInterface;

use function Amp\async;
use function Amp\delay;
use function Amp\Future\awaitAll;

#[AsCommand(
    name: 'maintain:queue:lottery',
    description: 'Maintains lottery queue using the AirlockSupervisor — handles promotion, eviction, and presence cleanup'
)]
class LotteryQueueMaintainCommand extends Command
{
    /**
     * How often to check the queue (seconds).
     */
    private const int POLL_INTERVAL = 2;

    /**
     * Must match the claim window in AirlockFactory.
     */
    private const int CLAIM_WINDOW = 5;

    /**
     * Must match the queue airlock slot limit in AirlockFactory.
     */
    private const int SLOT_LIMIT = 1;

    /**
     * Must match the queue airlock seal TTL in AirlockFactory.
     */
    private const int SEAL_TTL = 10;

    public function __construct(
        private AirlockFactory $airlockFactory,
        private HubInterface $hub,
    ) {
        parent::__construct();
    }

    public function __invoke(): void
    {
        $this->writeln('<info>Starting lottery queue supervisor...</info>');

        $airlock = $this->airlockFactory->redisLotteryQueue(
            limit: self::SLOT_LIMIT,
            ttl: self::SEAL_TTL,
            claimWindow: self::CLAIM_WINDOW,
        );

        $supervisor = $airlock->createSupervisor(
            notifier: new MercureAirlockNotifier($this->hub),
            claimWindowSeconds: self::CLAIM_WINDOW,
        );

        $futures = [];

        $futures[] = async(function () use ($supervisor): void {
            while (true) {
                delay(self::POLL_INTERVAL);

                $result = $supervisor->tick();

                foreach ($result->evicted as $evicted) {
                    $this->writeln(sprintf(
                        '<comment>[supervisor][%s]</comment> Evicted <info>%s</info> — notified but never claimed',
                        new DateTimeImmutable()->format('Y-m-d H:i:s'),
                        $evicted,
                    ));
                }

                if ($result->notified === null) {
                    continue;
                }

                $this->writeln(sprintf(
                    '<comment>[supervisor][%s]</comment> Notifying candidate <info>%s</info>',
                    new DateTimeImmutable()->format('Y-m-d H:i:s'),
                    $result->notified,
                ));
            }
        });

        awaitAll($futures);
    }
}
