<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Form\Type;

use Setono\SyliusTermsPlugin\Form\EventSubscriber\FillBlankSlugSubscriber;
use Setono\SyliusTermsPlugin\Generator\SlugGeneratorInterface;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

final class TermsTranslationType extends AbstractResourceType
{
    /**
     * @param list<string> $validationGroups
     */
    public function __construct(
        string $dataClass,
        private readonly SlugGeneratorInterface $slugGenerator,
        array $validationGroups = [],
    ) {
        parent::__construct($dataClass, $validationGroups);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'setono_sylius_terms.form.terms.name',
                'help' => 'setono_sylius_terms.form.terms.name_help',
            ])
            ->add('slug', TextType::class, [
                'label' => 'setono_sylius_terms.form.terms.slug',
                'help' => 'setono_sylius_terms.form.terms.slug_help',
                // A blank slug is filled from the name on submit
                'required' => false,
            ])
            ->add('label', TextType::class, [
                'label' => 'setono_sylius_terms.form.terms.label',
                'help' => 'setono_sylius_terms.form.terms.label_help',
            ])
            ->add('content', TextareaType::class, [
                'required' => false,
                'label' => 'setono_sylius_terms.form.terms.content',
                'help' => 'setono_sylius_terms.form.terms.content_help',
                'attr' => [
                    'rows' => 12,
                ],
            ])
            ->addEventSubscriber(new FillBlankSlugSubscriber($this->slugGenerator))
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_terms_terms_translation';
    }
}
