<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Setono\SyliusTermsPlugin\Uploader\ImageUploader;
use Setono\SyliusTermsPlugin\Uploader\ImageUploaderInterface;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(ImageUploader::class)
        ->args([
            // Configured in SetonoSyliusTermsExtension::prepend()
            service(ImageUploader::STORAGE),
            service('mime_types'),
        ])
    ;

    $services->alias(ImageUploaderInterface::class, ImageUploader::class);
};
