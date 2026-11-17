<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Setono\SyliusTermsPlugin\Controller\Action\ShowTermsAction;
use Setono\SyliusTermsPlugin\Lookup\TermsPageLookupInterface;
use Setono\SyliusTermsPlugin\Renderer\ContentRendererInterface;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(ShowTermsAction::class)
        ->public()
        ->args([
            service(TermsPageLookupInterface::class),
            service('sylius.context.channel'),
            service('sylius.context.locale'),
            service(ContentRendererInterface::class),
            service('twig'),
        ])
    ;
};
