<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Command;

use Redis;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'airlock:purge',
    description: 'EMERGENCY: Clears the waiting room queue and resets semaphore locks.',
)]
final class PurgeAirlockCommand extends Command
{
    public function __construct(
        private readonly Redis $redis,
        // Default values match your RedisFifoQueue defaults
        private readonly string $queueListKey = 'waiting_room:queue:list',
        private readonly string $queueSetKey = 'waiting_room:queue:set',
        // Default matches your SemaphoreSeal resource name
        private readonly string $semaphoreResource = 'waiting-room'
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip confirmation check');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->caution('This will kick EVERYONE out of the waiting room and clear all active locks.');

        if (!$input->getOption('force') && !$io->confirm('Are you absolutely sure you want to depressurize the system?', false)) {
            return Command::SUCCESS;
        }

        // 1. Clear the Queue (List + Set)
        $io->section('Clearing Queue...');
        $deletedList = $this->redis->del($this->queueListKey);
        $deletedSet = $this->redis->del($this->queueSetKey);

        if ($deletedList || $deletedSet) {
            $io->success('✅ Queue cleared.');
        } else {
            $io->info('ℹ️  Queue was already empty.');
        }

        // 2. Clear the Semaphores
        // Symfony Semaphore (RedisStore) usually stores keys as "semaphore:{resource}"
        // We delete the specific key for our resource.
        $io->section('Resetting Semaphores...');

        // Note: This relies on internal knowledge of how Symfony stores semaphores.
        // It's usually "semaphore:resource_name".
        $semaphoreKey = 'semaphore:' . $this->semaphoreResource;
        $deletedSem = $this->redis->del($semaphoreKey);

        if ($deletedSem) {
            $io->success(sprintf('✅ Semaphore lock for "%s" broken.', $this->semaphoreResource));
        } else {
            $io->info(sprintf('ℹ️  No active semaphore found for "%s".', $this->semaphoreResource));
        }

        $io->success('Airlock successfully purged. System is reset.');

        return Command::SUCCESS;
    }
}
