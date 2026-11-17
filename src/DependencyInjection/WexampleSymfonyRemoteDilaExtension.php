<?php

namespace Wexample\SymfonyRemoteDila\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Wexample\SymfonyHelpers\DependencyInjection\AbstractWexampleSymfonyExtension;

class WexampleSymfonyRemoteDilaExtension extends AbstractWexampleSymfonyExtension
{
    public function load(
        array $configs,
        ContainerBuilder $container
    ): void {
        $config = $this->processConfiguration(new Configuration(), $configs);
        $container->setParameter('wexample_symfony_remote_dila.cache_dir', $config['cache_dir']);
        $container->setParameter('wexample_symfony_remote_dila.an_debates.url', $config['an_debates']['url']);
        $container->setParameter('wexample_symfony_remote_dila.an_debates.attempts', $config['an_debates']['attempts']);
        $container->setParameter('wexample_symfony_remote_dila.an_debates.retry_delay', $config['an_debates']['retry_delay']);

        $this->loadConfig(
            __DIR__,
            $container
        );
    }
}
