<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Setono\SyliusTermsPlugin\Renderer\LabelRenderer;
use Setono\SyliusTermsPlugin\Renderer\LabelRendererInterface;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(LabelRenderer::class)
        ->args([
            service('router'),
        ])
    ;

    $services->alias(LabelRendererInterface::class, LabelRenderer::class);
};
