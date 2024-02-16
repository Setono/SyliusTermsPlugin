<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Form\Type;

use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

final class TermsTranslationType extends AbstractResourceType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'setono_sylius_terms.form.terms.name',
            ])
            ->add('slug', TextType::class, [
                'label' => 'setono_sylius_terms.form.terms.slug',
            ])
            ->add('label', TextType::class, [
                'label' => 'setono_sylius_terms.form.terms.label',
                'attr' => [
                    'placeholder' => 'setono_sylius_terms.form.terms.label_placeholder',
                ],
            ])
            ->add('content', TextareaType::class, [
                'required' => false,
                'label' => 'setono_sylius_terms.form.terms.content',
            ])
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_terms_terms_translation';
    }
}
