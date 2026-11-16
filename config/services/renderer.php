<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Setono\SyliusTermsPlugin\Converter\ContentConverter;
use Setono\SyliusTermsPlugin\Renderer\ContentRenderer;
use Setono\SyliusTermsPlugin\Renderer\ContentRendererInterface;
use Setono\SyliusTermsPlugin\Renderer\LabelRenderer;
use Setono\SyliusTermsPlugin\Renderer\LabelRendererInterface;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(ContentRenderer::class)
        ->args([
            service(ContentConverter::class),
            // Configured in SetonoSyliusTermsExtension::prepend()
            service('html_sanitizer.sanitizer.setono_sylius_terms.content'),
        ])
    ;

    $services->alias(ContentRendererInterface::class, ContentRenderer::class);

    $services->set(LabelRenderer::class)
        ->args([
            service('router'),
        ])
    ;

    $services->alias(LabelRendererInterface::class, LabelRenderer::class);
};
