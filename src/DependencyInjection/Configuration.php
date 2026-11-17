<?php

namespace Wexample\SymfonyRemoteDila\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Wexample\SymfonyRemoteDila\Service\DilaAnDebatesClient;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('wexample_symfony_remote_dila');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('cache_dir')
                    ->defaultValue('%kernel.project_dir%/var/remote/dila')
                    ->info('Where downloaded publications are unpacked and kept: out of the kernel\'s cache, which a deployment empties.')
                ->end()
                ->arrayNode('an_debates')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('url')->defaultValue(DilaAnDebatesClient::DEFAULT_URL)->end()
                        ->integerNode('attempts')->defaultValue(3)->min(1)->end()
                        ->integerNode('retry_delay')->defaultValue(5)->min(0)->info('Seconds before the next attempt, times the attempt number.')->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
