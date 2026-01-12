<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Bridge\Symfony\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('airlock');
        $rootNode = $treeBuilder->getRootNode();

        $this->configureInstanceNode($rootNode, 'airlock.workflow');

        $airlocksNode = $rootNode
            ->children()
                ->arrayNode('airlocks')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->addDefaultsIfNotSet();

        $this->configureInstanceNode($airlocksNode, null);

        $airlocksNode
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }

    private function configureInstanceNode(ArrayNodeDefinition $node, ?string $defaultServiceId): void
    {
        $node
            ->children()
                ->scalarNode('service_id')->defaultValue($defaultServiceId)->end()
                ->scalarNode('alias')->defaultNull()->end()
                ->scalarNode('topic_prefix')->defaultValue('/waiting-room')->end()
            ->end();

        $this->addSealSection($node);
        $this->addQueueSection($node);
        $this->addNotifierSection($node);
    }

    private function addSealSection(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('seal')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('type')->values(['semaphore'])->defaultValue('semaphore')->end()
                        ->arrayNode('semaphore')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('factory')->defaultValue('semaphore.workflow.factory')->end()
                                ->scalarNode('resource')->defaultValue('waiting-room')->end()
                                ->integerNode('limit')->defaultValue(1)->end()
                                ->integerNode('weight')->defaultValue(1)->end()
                                ->floatNode('ttl_in_seconds')->defaultValue(300.0)->end()
                                ->booleanNode('auto_release')->defaultFalse()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    private function addQueueSection(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('queue')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('type')->values(['redis_fifo', 'redis_lottery'])->defaultValue('redis_fifo')->end()
                        ->arrayNode('redis_fifo')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('redis')->defaultValue('redis')->end()
                                ->scalarNode('list_key')->defaultValue('waiting_room:queue:list')->end()
                                ->scalarNode('set_key')->defaultValue('waiting_room:queue:set')->end()
                            ->end()
                        ->end()
                        ->arrayNode('redis_lottery')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('redis')->defaultValue('redis')->end()
                                ->scalarNode('set_key')->defaultValue('airlock:pool')->end()
                                ->scalarNode('candidate_key')->defaultValue('airlock:pool:candidate')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    private function addNotifierSection(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('notifier')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('type')->values(['mercure', 'null'])->defaultValue('null')->end()
                        ->arrayNode('mercure')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('hub')->defaultValue('mercure.hub.default')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }
}
