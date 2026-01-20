<?php

declare(strict_types=1);

namespace App\Command;

use App\Infrastructure\StatusStore;
use Redis;
use RedisException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(
    name: 'app:worker',
    description: 'Run the Airlock examples worker'
)]
final class WorkerCommand extends Command
{
    public function __construct(
        private readonly Redis $redis,
        private readonly StatusStore $statusStore
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('queue', null, InputOption::VALUE_REQUIRED, 'Queue key', 'airlock:examples:jobs')
            ->addOption('once', null, InputOption::VALUE_NONE, 'Process a single job and exit');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $queueKey = (string) $input->getOption('queue');
        $once = (bool) $input->getOption('once');

        $output->writeln(sprintf('Airlock examples worker running. Queue: %s', $queueKey));

        $handlers = [
            '01-global-lock' => require dirname(__DIR__) . '/GlobalLock/Handler.php',
            '05-redis-lottery-queue' => require dirname(__DIR__) . '/RedisLotteryQueue/Handler.php',
        ];

        while (true) {
            try {
                $item = $this->redis->brPop([$queueKey], 5);
                if (!$item) {
                    $output->write('.');
                    if ($once) {
                        $output->writeln('');
                        return Command::SUCCESS;
                    }
                    continue;
                }

                $job = json_decode($item[1], true, 512, JSON_THROW_ON_ERROR);
                $example = $job['example'] ?? null;
                $clientId = $job['clientId'] ?? 'unknown';
                $output->writeln(sprintf("\n[worker] Received job: example=%s, clientId=%s", $example, $clientId));

                if (!is_string($example) || !isset($handlers[$example])) {
                    $output->writeln(sprintf('[worker] No handler for example: %s', (string) $example));
                    if ($once) {
                        return Command::SUCCESS;
                    }
                    continue;
                }

                $output->writeln('[worker] Processing with handler...');

                $handlerRedis = $this->redis;

                $handlers[$example]($handlerRedis, $job, function (
                    string $example,
                    string $clientId,
                    array $status
                ): void {
                    $this->statusStore->set($example, $clientId, $status);
                });

                $output->writeln('[worker] Job completed');

                if ($once) {
                    return Command::SUCCESS;
                }
            } catch (RedisException $e) {
                $output->writeln(sprintf("\n[worker] Redis error: %s, reconnecting...", $e->getMessage()));
                sleep(1);
            } catch (Throwable $e) {
                $output->writeln(sprintf("\n[worker] Error: %s", $e->getMessage()));
                sleep(1);
            }
        }
    }
}
