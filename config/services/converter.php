<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Setono\SyliusTermsPlugin\Converter\ContentConverter;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // The plugin's own CommonMark converter. The app-wide twig_extra one has unsafe defaults
    $services->set(ContentConverter::class);
};
