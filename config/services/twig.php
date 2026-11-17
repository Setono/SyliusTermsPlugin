<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Setono\SyliusTermsPlugin\Generator\SlugGeneratorInterface;
use Setono\SyliusTermsPlugin\Lookup\TermsPageLookupInterface;
use Setono\SyliusTermsPlugin\Renderer\ContentRendererInterface;
use Setono\SyliusTermsPlugin\Twig\Component\TermsFormComponent;
use Setono\SyliusTermsPlugin\Twig\ContentRuntime;
use Setono\SyliusTermsPlugin\Twig\TermsExtension;
use Setono\SyliusTermsPlugin\Twig\TermsRuntime;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(TermsExtension::class)
        ->tag('twig.extension')
    ;

    $services->set(TermsRuntime::class)
        ->args([
            service(TermsPageLookupInterface::class),
            service('sylius.context.channel'),
            service('sylius.context.locale'),
            service('router'),
        ])
        ->tag('twig.runtime')
    ;

    $services->set(ContentRuntime::class)
        ->args([
            service(ContentRendererInterface::class),
        ])
        ->tag('twig.runtime')
    ;

    $services->set(TermsFormComponent::class)
        ->args([
            service('setono_sylius_terms.repository.terms'),
            service('form.factory'),
            param('setono_sylius_terms.model.terms.class'),
            param('setono_sylius_terms.form.terms.class'),
            service(SlugGeneratorInterface::class),
        ])
        ->tag('sylius.live_component.admin', ['key' => 'setono_sylius_terms:admin:terms:form'])
    ;
};
