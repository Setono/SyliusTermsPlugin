<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Setono\SyliusTermsPlugin\Provider\TermsProvider;
use Setono\SyliusTermsPlugin\Provider\TermsProviderInterface;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(TermsProvider::class)
        ->args([
            service('setono_sylius_terms.repository.terms'),
            service('sylius.context.channel'),
            service('sylius.context.locale'),
        ])
    ;

    $services->alias(TermsProviderInterface::class, TermsProvider::class);
};
