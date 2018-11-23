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
            ->integerNode('runtime')->defaultValue(3600)->info('Seconds this runner is active before automatic shutdown (minimum)')->end()
            ->integerNode('fuzz')->defaultValue(600)->info('A random number of between 0 and this number of seconds will be added to the runtime to ensure not all runners will timeout at the same time.')->end()
            ->floatNode('timeout')->defaultValue(2.0)->info('After this times of the runtime+fuzz the runner is considered timed out.')->end()
            ->integerNode('block')->defaultValue(10)->info('Number of seconds connections keeps block if queues are empty.')->end()
            ->booleanNode('autorecover')->defaultFalse()->info('If set to true a timed out runner will restart automatically.')->end()
          ->end()
        ->end()
        ->arrayNode('queue')->addDefaultsIfNotSet()
          ->children()
            ->scalarNode('prefix')->defaultValue('queue.')->info('Prefix for queue names.')->end()
            ->arrayNode('priorities')
              ->info('An array of string defining the queue priorities (from highest priority to lowest). For example: [high, normal, low]')
              ->prototype('scalar')->end()
              ->defaultValue(['high', 'normal', 'low'])
            ->end()
          ->end()
        ->end()
        ->arrayNode('logger')->addDefaultsIfNotSet()
          ->children()
            ->scalarNode('delimiter')->defaultValue('$')->end()
            ->scalarNode('runner')->defaultValue('[RUNNER "$RUNNER_ID$"] ')->end()
            ->scalarNode('job')->defaultValue('[JOB "$JOB_ID$"] ')->end()
            ->scalarNode('format')->defaultValue('$PREFIX$$RUNNER$$JOB$$LOG$$POSTFIX$')->info('Possible replacments are: [$PREFIX$, $JOB$, $LOG$, $RUNNER$, $POSTFIX$]')->end()
          ->end()
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
            ->scalarNode('subject')->defaultValue('Async job finished')->end()
            ->scalarNode('text2html')->defaultFalse()->info('Automatically adds the nl2br-text-version as html part of the email.')->end()
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
