<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Setono\SyliusTermsPlugin\Controller\Action\ShowTermsAction;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('setono_sylius_terms.controller.action.show_terms', ShowTermsAction::class)
        ->public()
        ->args([
            service('setono_sylius_terms.repository.terms'),
            service('sylius.context.channel'),
            service('sylius.context.locale'),
            service('twig'),
        ])
    ;
};
