<?php

declare(strict_types=1);

namespace App\Command;

use App\Infrastructure\RedisFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:reset',
    description: 'Flush all Redis data used by the examples'
)]
final class ResetCommand extends Command
{
    public function __construct(private readonly RedisFactory $redisFactory)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $redis = $this->redisFactory->create();
        $redis->flushDB();

        $output->writeln('Redis database flushed.');

        return Command::SUCCESS;
    }
}
