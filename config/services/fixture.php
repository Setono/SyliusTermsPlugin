<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Setono\SyliusTermsPlugin\Fixture\Factory\TermsExampleFactory;
use Setono\SyliusTermsPlugin\Fixture\TermsFixture;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('setono_sylius_terms.fixture.example_factory.terms', TermsExampleFactory::class)
        ->args([
            service('setono_sylius_terms.factory.terms'),
            service('setono_sylius_terms.repository.terms'),
            service('sylius.repository.locale'),
            service('sylius.repository.channel'),
        ])
    ;

    $services->set('setono_sylius_terms.fixture.terms', TermsFixture::class)
        ->args([
            service('setono_sylius_terms.manager.terms'),
            service('setono_sylius_terms.fixture.example_factory.terms'),
        ])
        ->tag('sylius_fixtures.fixture')
    ;
};
