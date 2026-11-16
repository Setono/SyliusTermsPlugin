<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Setono\SyliusTermsPlugin\Provider\TermsProvider;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('setono_sylius_terms.provider.terms', TermsProvider::class)
        ->args([
            service('setono_sylius_terms.repository.terms'),
            service('sylius.context.channel'),
            service('sylius.context.locale'),
        ])
    ;
};
