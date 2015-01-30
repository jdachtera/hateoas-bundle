<?php
namespace uebb\HateoasBundle\DependencyInjection\Extension;

/**
 * Created by PhpStorm.
 * User: uberblick
 * Date: 30.01.15
 * Time: 12:27
 */

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class HateoasExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );

        $loader->load('config.yml');
    }
}