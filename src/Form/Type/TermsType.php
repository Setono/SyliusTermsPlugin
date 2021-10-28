<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Form\Type;

use Sylius\Bundle\ChannelBundle\Form\Type\ChannelChoiceType;
use Sylius\Bundle\ResourceBundle\Form\EventSubscriber\AddCodeFormSubscriber;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Sylius\Bundle\ResourceBundle\Form\Type\ResourceTranslationsType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;

final class TermsType extends AbstractResourceType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('channels', ChannelChoiceType::class, [
                'multiple' => true,
                'expanded' => true,
                'label' => 'setono_sylius_terms.form.terms.channels',
            ])
            ->add('enabled', CheckboxType::class, [
                'required' => true,
                'label' => 'setono_sylius_terms.form.terms.enabled',
            ])
            ->add('position', IntegerType::class, [
                'required' => false,
                'label' => 'setono_sylius_terms.form.terms.position',
            ])
            ->add('displayInFooter', CheckboxType::class, [
                'required' => true,
                'label' => 'setono_sylius_terms.form.terms.display_in_footer',
            ])
            ->add('displayInCheckout', CheckboxType::class, [
                'required' => true,
                'label' => 'setono_sylius_terms.form.terms.display_in_checkout',
            ])
            ->add('displayInCustomerSignup', CheckboxType::class, [
                'required' => true,
                'label' => 'setono_sylius_terms.form.terms.display_in_customer_signup',
            ])
            ->add('translations', ResourceTranslationsType::class, [
                'entry_type' => TermsTranslationType::class,
                'label' => 'setono_sylius_terms.form.terms.translations',
            ])
            ->addEventSubscriber(new AddCodeFormSubscriber(null, [
                'label' => 'setono_sylius_terms.form.terms.code',
            ]))
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_terms_terms';
    }
}
