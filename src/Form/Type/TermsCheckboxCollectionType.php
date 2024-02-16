<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Form\Type;

use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\PreSetDataEvent;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;

final class TermsCheckboxCollectionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (PreSetDataEvent $event) use ($options): void {
            $form = $event->getForm();
            $parent = $form->getParent();

            $validationGroups = [];
            while (null !== $parent) {
                $groups = $parent->getConfig()->getOption('validation_groups');
                if (!is_array($groups)) {
                    continue;
                }

                $validationGroups[] = $groups;
                $parent = $parent->getParent();
            }

            $validationGroups = array_filter(array_merge(...$validationGroups), static fn (mixed $group): bool => is_string($group));

            /** @var TermsInterface $term */
            foreach ($options['terms'] as $term) {
                $form->add((string) $term->getCode(), CheckboxType::class, [
                    'label' => $term->getLabel(),
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
