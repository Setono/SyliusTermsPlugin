<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Form\Type;

use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Sylius\Bundle\ChannelBundle\Form\Type\ChannelChoiceType;
use Sylius\Bundle\ResourceBundle\Form\EventSubscriber\AddCodeFormSubscriber;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Sylius\Bundle\ResourceBundle\Form\Type\ResourceTranslationsType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

final class TermsType extends AbstractResourceType
{
    /** @var array<string, string> The labels of the configured Forms by their code */
    private array $formLabels = [];

    /**
     * @param array<string, array{label: string}> $forms
     * @param list<string> $validationGroups
     */
    public function __construct(string $dataClass, array $forms, array $validationGroups = [])
    {
        parent::__construct($dataClass, $validationGroups);

        foreach ($forms as $code => $form) {
            $this->formLabels[$code] = $form['label'];
        }
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('enabled', CheckboxType::class, [
                'label' => 'sylius.ui.enabled',
                'required' => false,
            ])
            ->add('required', CheckboxType::class, [
                'label' => 'setono_sylius_terms.form.terms.required',
                'help' => 'setono_sylius_terms.form.terms.required_help',
                'required' => false,
            ])
            ->add('position', IntegerType::class, [
                'label' => 'setono_sylius_terms.form.terms.position',
                'help' => 'setono_sylius_terms.form.terms.position_help',
                'required' => false,
            ])
            ->add('channels', ChannelChoiceType::class, [
                'multiple' => true,
                'expanded' => true,
                'label' => 'setono_sylius_terms.form.terms.channels',
                'required' => false,
            ])
            ->add('translations', ResourceTranslationsType::class, [
                'entry_type' => TermsTranslationType::class,
                'label' => 'setono_sylius_terms.form.terms.translations',
            ])
            ->addEventSubscriber(new AddCodeFormSubscriber(null, [
                'label' => 'setono_sylius_terms.form.terms.code',
            ]))
            // The Forms field is added once the Terms is known, because its choices include the Terms' own Form codes
            ->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
                $terms = $event->getData();

                $event->getForm()->add('forms', ChoiceType::class, $this->getFormsOptions(
                    $terms instanceof TermsInterface ? $terms->getForms() : [],
                ));
            })
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_terms_terms';
    }

    /**
     * The choices are the configured Forms, followed by the codes the Terms is placed on that aren't configured (any
     * more). Those show as "<code> (not configured)", ticked, and stay on the Terms until an admin unticks them
     *
     * @param list<string> $storedForms
     *
     * @return array<string, mixed>
     */
    private function getFormsOptions(array $storedForms): array
    {
        $codes = array_keys($this->formLabels);
        foreach ($storedForms as $storedForm) {
            if (!isset($this->formLabels[$storedForm])) {
                $codes[] = $storedForm;
            }
        }

        return [
            'label' => 'setono_sylius_terms.form.terms.forms',
            'choices' => $codes,
            'choice_label' => fn (string $code): string => $this->formLabels[$code] ?? 'setono_sylius_terms.form.terms.unconfigured_form',
            'choice_translation_parameters' => fn (string $code): array => isset($this->formLabels[$code]) ? [] : ['%code%' => $code],
            'required' => false,
            'expanded' => true,
            'multiple' => true,
        ];
    }
}
