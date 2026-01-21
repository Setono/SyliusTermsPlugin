<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Form\Type;

use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Setono\SyliusTermsPlugin\Renderer\LabelRendererInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\PreSetDataEvent;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;

final class TermsCheckboxCollectionType extends AbstractType
{
    public function __construct(private readonly LabelRendererInterface $labelRenderer)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // The only reason we add the terms in an event listener
        // is to be able to collect the validation groups from the parent forms
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (PreSetDataEvent $event) use ($options): void {
            $form = $event->getForm();
            $parent = $form->getParent();

            // We need to collect all validation groups from the parent forms, so that our constraints are applied
            $validationGroups = [];
            while (null !== $parent) {
                $groups = $parent->getConfig()->getOption('validation_groups');

                if (is_array($groups)) {
		            $validationGroups[] = $groups;
                }

                $parent = $parent->getParent();
            }

            $validationGroups = array_filter(array_merge(...$validationGroups), static fn (mixed $group): bool => is_string($group));

            /** @var TermsInterface $terms */
            foreach ($options['terms'] as $terms) {
                $form->add((string) $terms->getCode(), CheckboxType::class, [
                    'label' => $this->labelRenderer->render($terms),
                    'label_html' => true,
                    'required' => false,
                    'value' => true,
                    'mapped' => false,
                    'constraints' => [
                        new IsTrue([
                            'message' => $options['error_message'],
                            'groups' => $validationGroups,
                        ]),
                    ],
                ]);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired('terms')
            ->setAllowedTypes('terms', ['array'])
            ->setDefaults([
                'label' => 'setono_sylius_terms.form.terms_accept.label',
                'mapped' => false,
                'terms' => [],
                'error_message' => 'setono_sylius_terms.terms_checkbox.required',
            ])
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_terms_terms_checkbox_collection';
    }
}
