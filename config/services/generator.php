<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Setono\SyliusTermsPlugin\Generator\SlugGenerator;
use Setono\SyliusTermsPlugin\Generator\SlugGeneratorInterface;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(SlugGenerator::class)
        ->args([
            service('slugger'),
        ])
    ;

    $services->alias(SlugGeneratorInterface::class, SlugGenerator::class);
};
