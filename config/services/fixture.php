<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Setono\SyliusTermsPlugin\Fixture\Factory\TermsExampleFactory;
use Setono\SyliusTermsPlugin\Fixture\TermsFixture;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(TermsExampleFactory::class)
        ->args([
            service('setono_sylius_terms.factory.terms'),
            service('setono_sylius_terms.repository.terms'),
            service('sylius.repository.locale'),
            service('sylius.repository.channel'),
            param('setono_sylius_terms.forms'),
        ])
    ;

    $services->set(TermsFixture::class)
        ->args([
            service('setono_sylius_terms.manager.terms'),
            service(TermsExampleFactory::class),
        ])
        ->tag('sylius_fixtures.fixture')
    ;
};
