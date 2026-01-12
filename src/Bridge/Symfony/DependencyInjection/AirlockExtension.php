<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Bridge\Symfony\DependencyInjection;

use Clegginabox\Airlock\AirlockInterface;
use Clegginabox\Airlock\Bridge\Mercure\MercureAirlockNotifier;
use Clegginabox\Airlock\Notifier\AirlockNotifierInterface;
use Clegginabox\Airlock\Notifier\NullAirlockNotifier;
use Clegginabox\Airlock\Queue\QueueInterface;
use Clegginabox\Airlock\Queue\RedisFifoQueue;
use Clegginabox\Airlock\Queue\RedisLotteryQueue;
use Clegginabox\Airlock\QueueAirlock;
use Clegginabox\Airlock\Seal\SealInterface;
use Clegginabox\Airlock\Seal\SemaphoreSeal;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

final class AirlockExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $processor = new Processor();
        $config = $processor->processConfiguration($configuration, $configs);

        if ($config['airlocks'] === []) {
            $registered = [];

            foreach ($config['airlocks'] as $name => $airlockConfig) {
                $registered[] = $this->registerAirlock($container, $airlockConfig, (string) $name);
            }

            if (count($registered) === 1) {
                $ids = $registered[0];
                $this->registerInterfaceAliases($container, $ids['airlock'], $ids['seal'], $ids['queue'], $ids['notifier']);
            }

            return;
        }

        $ids = $this->registerAirlock($container, $config, 'default');
        $this->registerInterfaceAliases($container, $ids['airlock'], $ids['seal'], $ids['queue'], $ids['notifier']);
    }

    /**
     * @return array{airlock: string, seal: string, queue: string, notifier: string}
     */
    private function registerAirlock(ContainerBuilder $container, array $config, string $name): array
    {
        $prefix = $name === 'default' ? 'airlock' : sprintf('airlock.%s', $name);
        $sealId = sprintf('%s.seal', $prefix);
        $queueId = sprintf('%s.queue', $prefix);
        $notifierId = sprintf('%s.notifier', $prefix);
        $airlockId = $config['service_id'] ?? sprintf('airlock.%s', $name);

        $container->setDefinition($sealId, $this->createSealDefinition($config));
        $container->setDefinition($queueId, $this->createQueueDefinition($config));
        $container->setDefinition($notifierId, $this->createNotifierDefinition($config));

        $airlockDefinition = new Definition(QueueAirlock::class);
        $airlockDefinition->setArguments([
            new Reference($sealId),
            new Reference($queueId),
            new Reference($notifierId),
            $config['topic_prefix'],
        ]);
        $airlockDefinition->setPublic(true);

        $container->setDefinition($airlockId, $airlockDefinition);

        if ($config['alias'] !== null) {
            $container->setAlias($config['alias'], $airlockId)->setPublic(true);
        }

        return [
            'airlock' => $airlockId,
            'seal' => $sealId,
            'queue' => $queueId,
            'notifier' => $notifierId,
        ];
    }

    private function registerInterfaceAliases(
        ContainerBuilder $container,
        string $airlockId,
        string $sealId,
        string $queueId,
        string $notifierId,
    ): void {
        $container->setAlias(AirlockInterface::class, $airlockId)->setPublic(true);
        $container->setAlias(SealInterface::class, $sealId);
        $container->setAlias(QueueInterface::class, $queueId);
        $container->setAlias(AirlockNotifierInterface::class, $notifierId);
    }

    private function createSealDefinition(array $config): Definition
    {
        $sealConfig = $config['seal']['semaphore'];

        $definition = new Definition(SemaphoreSeal::class);
        $definition->setArguments([
            new Reference($sealConfig['factory']),
            $sealConfig['resource'],
            $sealConfig['limit'],
            $sealConfig['weight'],
            $sealConfig['ttl_in_seconds'],
            $sealConfig['auto_release'],
        ]);

        return $definition;
    }

    private function createQueueDefinition(array $config): Definition
    {
        if ($config['queue']['type'] === 'redis_lottery') {
            $queueConfig = $config['queue']['redis_lottery'];

            $definition = new Definition(RedisLotteryQueue::class);
            $definition->setArguments([
                new Reference($queueConfig['redis']),
                $queueConfig['set_key'],
                $queueConfig['candidate_key'],
            ]);

            return $definition;
        }

        $queueConfig = $config['queue']['redis_fifo'];

        $definition = new Definition(RedisFifoQueue::class);
        $definition->setArguments([
            new Reference($queueConfig['redis']),
            $queueConfig['list_key'],
            $queueConfig['set_key'],
        ]);

        return $definition;
    }

    private function createNotifierDefinition(array $config): Definition
    {
        if ($config['notifier']['type'] === 'mercure') {
            $notifierConfig = $config['notifier']['mercure'];

            $definition = new Definition(MercureAirlockNotifier::class);
            $definition->setArguments([new Reference($notifierConfig['hub'])]);

            return $definition;
        }

        return new Definition(NullAirlockNotifier::class);
    }
}
