<?php

namespace HBM\AsyncWorkerBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface {

  /**
   * {@inheritdoc}
   */
  public function getConfigTreeBuilder() {
    $treeBuilder = new TreeBuilder();
    $rootNode = $treeBuilder->root('hbm_async_worker');

    $rootNode
      ->children()
        ->arrayNode('runner')->addDefaultsIfNotSet()
          ->children()
            ->arrayNode('ids')
              ->info('An array of runner names. Can be simple numbers or string names.')
              ->prototype('scalar')->end()
              ->defaultValue(['main'])
            ->end()
            ->scalarNode('runtime')->defaultValue(3600)->info('Seconds this runner is active before automatic shutdown (minimum)')->end()
            ->scalarNode('fuzz')->defaultValue(600)->info('A random number of between 0 and this number of seconds will be added to the runtime to ensure not all runners will timeout at the same time.')->end()
            ->scalarNode('timeout')->defaultValue(2.0)->info('After this times of the runtime+fuzz the runner is considered timed out.')->end()
            ->scalarNode('block')->defaultValue(10)->info('Number of seconds connections keeps block if queues are empty.')->end()
          ->end()
        ->end()
        ->arrayNode('priorities')
          ->info('An array of string defining the queue priorities (from highest priority to lowest). For example: [queue.priority.high, queue.priority.medium, queue.priority.low]')
          ->prototype('scalar')->end()
          ->defaultValue(['low', 'normal', 'high'])
        ->end()
        ->arrayNode('error')->addDefaultsIfNotSet()
          ->children()
            ->scalarNode('log')->defaultFalse()->end()
            ->scalarNode('file')->defaultValue('/var/log/php-async-worker.log')->end()
          ->end()
        ->end()
        ->arrayNode('mail')->addDefaultsIfNotSet()
          ->children()
            ->scalarNode('to')->defaultNull()->end()
            ->scalarNode('fromName')->defaultNull()->end()
            ->scalarNode('fromAddress')->defaultNull()->end()
          ->end()
        ->end()
        ->arrayNode('output')->addDefaultsIfNotSet()
          ->children()
            ->arrayNode('formats')->defaultValue([])->useAttributeAsKey('level')
              ->prototype('array')
                ->children()
                  ->scalarNode('level')->end()
                  ->scalarNode('fg')->defaultNull()->end()
                  ->scalarNode('bg')->defaultNull()->end()
                  ->arrayNode('options')
                    ->prototype('scalar')->end()
                    ->defaultValue([])
                  ->end()
                ->end()
              ->end()
            ->end()
          ->end()
        ->end()
      ->end()
    ->end();

    return $treeBuilder;
  }

}
