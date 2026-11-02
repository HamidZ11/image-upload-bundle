<?php

namespace Hamid\ImageUploadBundle;

use Hamid\ImageUploadBundle\Form\ImageUploadType;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

final class ImageUploadBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()->children()
            ->scalarNode('directory')->isRequired()->cannotBeEmpty()->end()
            ->scalarNode('max_size')->defaultValue('5M')->cannotBeEmpty()->end()
            ->integerNode('max_pixels')->min(1)->defaultValue(10_000_000)->end()
        ->end();
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services()->defaults()->autowire();
        $services->set(ImageUploader::class)
            ->arg('$directory', $config['directory'])
            ->arg('$maxSize', $config['max_size'])
            ->arg('$maxPixels', $config['max_pixels'])
            ->arg('$logger', service('logger')->nullOnInvalid());
        $services->set(ImageUploadType::class)->tag('form.type');
    }
}
