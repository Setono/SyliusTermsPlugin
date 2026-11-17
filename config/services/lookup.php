<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Setono\SyliusTermsPlugin\Lookup\FormTermsLookup;
use Setono\SyliusTermsPlugin\Lookup\FormTermsLookupInterface;
use Setono\SyliusTermsPlugin\Lookup\TermsPageLookup;
use Setono\SyliusTermsPlugin\Lookup\TermsPageLookupInterface;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(FormTermsLookup::class)
        ->args([
            service('setono_sylius_terms.repository.terms'),
            service('setono_sylius_terms.repository.terms_version'),
            service('clock'),
        ])
    ;

    $services->alias(FormTermsLookupInterface::class, FormTermsLookup::class);

    $services->set(TermsPageLookup::class)
        ->args([
            service('setono_sylius_terms.repository.terms'),
            service('setono_sylius_terms.repository.terms_version'),
            service('clock'),
        ])
    ;

    $services->alias(TermsPageLookupInterface::class, TermsPageLookup::class);
};
