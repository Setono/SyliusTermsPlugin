<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Form\Type;

use Sylius\Bundle\ChannelBundle\Form\Type\ChannelChoiceType;
use Sylius\Bundle\ResourceBundle\Form\EventSubscriber\AddCodeFormSubscriber;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Sylius\Bundle\ResourceBundle\Form\Type\ResourceTranslationsType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormTypeInterface;

final class TermsType extends AbstractResourceType
{
    /** @param array<string, class-string<FormTypeInterface>> $forms */
    private array $forms = [];

    /**
     * @param array<class-string<FormTypeInterface>, array{label: string}> $forms
     * @param list<string> $validationGroups
     */
    public function __construct(string $dataClass, array $forms, array $validationGroups = [])
    {
        parent::__construct($dataClass, $validationGroups);

        foreach ($forms as $form => $config) {
            $this->forms[$config['label']] = $form;
        }
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('enabled', CheckboxType::class, [
                'label' => 'sylius.ui.enabled',
                'required' => false,
            ])
            ->add('channels', ChannelChoiceType::class, [
                'multiple' => true,
                'expanded' => true,
                'label' => 'setono_sylius_terms.form.terms.channels',
                'required' => false,
            ])
            ->add('forms', ChoiceType::class, [
                'label' => 'setono_sylius_terms.form.terms.forms',
                'choices' => $this->forms,
                'required' => false,
                'expanded' => true,
                'multiple' => true,
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
