<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Setono\SyliusTermsPlugin\Form\Extension\FormTypeExtension;
use Setono\SyliusTermsPlugin\Form\Type\TermsCheckboxCollectionType;
use Setono\SyliusTermsPlugin\Form\Type\TermsTranslationType;
use Setono\SyliusTermsPlugin\Form\Type\TermsType;

return static function (ContainerConfigurator $container): void {
    $container->parameters()
        ->set('setono_sylius_terms.form.type.terms.validation_groups', ['setono_sylius_terms'])
        ->set('setono_sylius_terms.form.type.terms_translation.validation_groups', ['setono_sylius_terms'])
    ;

    $services = $container->services();

    $services->set('setono_sylius_terms.form.type.terms', TermsType::class)
        ->args([
            param('setono_sylius_terms.model.terms.class'),
            param('setono_sylius_terms.forms'),
            param('setono_sylius_terms.form.type.terms.validation_groups'),
        ])
        ->tag('form.type')
    ;

    $services->set('setono_sylius_terms.form.type.terms_translation', TermsTranslationType::class)
        ->args([
            param('setono_sylius_terms.model.terms_translation.class'),
            param('setono_sylius_terms.form.type.terms_translation.validation_groups'),
        ])
        ->tag('form.type')
    ;

    $services->set('setono_sylius_terms.form.type.terms_checkbox_collection', TermsCheckboxCollectionType::class)
        ->args([
            service('setono_sylius_terms.renderer.label'),
        ])
        ->tag('form.type')
    ;

    $services->set(FormTypeExtension::class)
        ->args([
            service('setono_sylius_terms.provider.terms'),
            param('setono_sylius_terms.forms'),
        ])
        ->tag('form.type_extension')
    ;
};
