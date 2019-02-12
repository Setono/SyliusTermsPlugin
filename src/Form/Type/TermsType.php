<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Form\Type;

use Sylius\Bundle\ChannelBundle\Form\Type\ChannelChoiceType;
use Sylius\Bundle\ResourceBundle\Form\EventSubscriber\AddCodeFormSubscriber;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Sylius\Bundle\ResourceBundle\Form\Type\ResourceTranslationsType;
use Symfony\Component\Form\FormBuilderInterface;

final class TermsType extends AbstractResourceType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('channel', ChannelChoiceType::class, [
                'label' => 'setono_sylius_terms.form.terms.channel',
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

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'setono_sylius_terms_terms';
    }
}
