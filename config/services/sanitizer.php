<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Setono\SyliusTermsPlugin\Sanitizer\HeadingIdAttributeSanitizer;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // Registered on the setono_sylius_terms.content sanitizer in SetonoSyliusTermsExtension::prepend()
    $services->set(HeadingIdAttributeSanitizer::class);
};
