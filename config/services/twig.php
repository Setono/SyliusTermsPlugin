<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Setono\SyliusTermsPlugin\Twig\TermsExtension;
use Setono\SyliusTermsPlugin\Twig\TermsRuntime;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('setono_sylius_terms.twig.terms_extension', TermsExtension::class)
        ->tag('twig.extension')
    ;

    $services->set('setono_sylius_terms.twig.terms_runtime', TermsRuntime::class)
        ->args([
            service('sylius.context.channel'),
            service('sylius.context.locale'),
            service('setono_sylius_terms.repository.terms'),
        ])
        ->tag('twig.runtime')
    ;
};
