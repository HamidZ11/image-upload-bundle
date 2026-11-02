<?php

namespace Hamid\ImageUploadBundle\Tests\Fixtures;

use Hamid\ImageUploadBundle\ImageUploadBundle;
use Hamid\ImageUploadBundle\ImageUploader;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

final class HostKernel extends Kernel
{
    public function __construct(
        private readonly string $temporaryDirectory,
        private readonly string $maxSize = '5M',
        private readonly int $maxPixels = 10_000_000,
    ) {
        parent::__construct('test', true);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new ImageUploadBundle();
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', [
                'secret' => 'image-upload-test',
                'test' => true,
                'form' => true,
                'validation' => ['enabled' => true],
            ]);
            $container->loadFromExtension('image_upload', [
                'directory' => $this->temporaryDirectory.'/images',
                'max_size' => $this->maxSize,
                'max_pixels' => $this->maxPixels,
            ]);
            $container->register('logger', NullLogger::class);
            $container->setAlias('test.image_uploader', ImageUploader::class)->setPublic(true);
            $container->setAlias('test.form_factory', 'form.factory')->setPublic(true);
        });
    }

    public function getProjectDir(): string
    {
        return dirname(__DIR__, 2);
    }

    protected function getContainerClass(): string
    {
        // Separate cache directories can still produce the same normalized container class.
        return parent::getContainerClass().'_'.hash('sha256', serialize([
            $this->temporaryDirectory,
            $this->maxSize,
            $this->maxPixels,
        ]));
    }

    public function getCacheDir(): string
    {
        return $this->temporaryDirectory.'/cache';
    }

    public function getLogDir(): string
    {
        return $this->temporaryDirectory.'/log';
    }
}
