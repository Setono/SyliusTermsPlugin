<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Setono\SyliusTermsPlugin\Renderer\LabelRenderer;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('setono_sylius_terms.renderer.label', LabelRenderer::class)
        ->args([
            service('router'),
        ])
    ;
};
