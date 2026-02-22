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
    name: 'maintain:queue:fifo',
    description: 'Maintains fifo queue using the AirlockSupervisor — handles promotion, eviction, and presence cleanup'
)]
class FifoQueueMaintainCommand extends Command
{
    /**
     * How often to check the queue (seconds).
     */
    private const int POLL_INTERVAL = 2;

    /**
     * Must match the claim window in AirlockFactory.
     */
    private const int CLAIM_WINDOW = 5;

    public function __construct(
        private AirlockFactory $airlockFactory,
        private HubInterface $hub,
    ) {
        parent::__construct();
    }

    public function __invoke(): void
    {
        $this->writeln('<info>Starting FIFO queue supervisor...</info>');

        $airlock = $this->airlockFactory->redisFifoQueue();

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
                        '<comment>[supervisor][fifo][%s]</comment> Evicted <info>%s</info> — notified but never claimed',
                        new DateTimeImmutable()->format('Y-m-d H:i:s'),
                        $evicted,
                    ));
                }

                if ($result->notified === null) {
                    continue;
                }

                $this->writeln(sprintf(
                    '<comment>[supervisor][fifo][%s]</comment> Notifying candidate <info>%s</info>',
                    new DateTimeImmutable()->format('Y-m-d H:i:s'),
                    $result->notified,
                ));
            }
        });

        awaitAll($futures);
    }
}
