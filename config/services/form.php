<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Setono\SyliusTermsPlugin\Form\Extension\FormTypeExtension;
use Setono\SyliusTermsPlugin\Form\Type\TermsCheckboxCollectionType;
use Setono\SyliusTermsPlugin\Form\Type\TermsTranslationType;
use Setono\SyliusTermsPlugin\Form\Type\TermsType;
use Setono\SyliusTermsPlugin\Generator\SlugGeneratorInterface;
use Setono\SyliusTermsPlugin\Lookup\FormTermsLookupInterface;
use Setono\SyliusTermsPlugin\Renderer\LabelRendererInterface;

return static function (ContainerConfigurator $container): void {
    $container->parameters()
        ->set('setono_sylius_terms.form.type.terms.validation_groups', ['setono_sylius_terms'])
        ->set('setono_sylius_terms.form.type.terms_translation.validation_groups', ['setono_sylius_terms'])
    ;

    $services = $container->services();

    $services->set(TermsType::class)
        ->args([
            param('setono_sylius_terms.model.terms.class'),
            param('setono_sylius_terms.forms'),
            param('setono_sylius_terms.form.type.terms.validation_groups'),
        ])
        ->tag('form.type')
    ;

    $services->set(TermsTranslationType::class)
        ->args([
            param('setono_sylius_terms.model.terms_translation.class'),
            service(SlugGeneratorInterface::class),
            param('setono_sylius_terms.form.type.terms_translation.validation_groups'),
        ])
        ->tag('form.type')
    ;

    $services->set(TermsCheckboxCollectionType::class)
        ->args([
            service(LabelRendererInterface::class),
        ])
        ->tag('form.type')
    ;

    $services->set(FormTypeExtension::class)
        ->args([
            service(FormTermsLookupInterface::class),
            service('sylius.context.channel'),
            service('sylius.context.locale'),
            param('setono_sylius_terms.forms'),
        ])
        ->tag('form.type_extension')
    ;
};
