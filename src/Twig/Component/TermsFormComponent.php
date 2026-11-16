<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Twig\Component;

use Setono\SyliusTermsPlugin\Generator\SlugGeneratorInterface;
use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Sylius\Bundle\UiBundle\Twig\Component\ResourceFormComponentTrait;
use Sylius\Bundle\UiBundle\Twig\Component\TemplatePropTrait;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;

/**
 * The admin create and update form of a Terms
 */
#[AsLiveComponent]
final class TermsFormComponent
{
    use TemplatePropTrait;

    /** @use ResourceFormComponentTrait<TermsInterface> */
    use ResourceFormComponentTrait;

    /**
     * @param RepositoryInterface<TermsInterface> $repository
     * @param class-string $resourceClass
     * @param class-string $formClass
     */
    public function __construct(
        RepositoryInterface $repository,
        FormFactoryInterface $formFactory,
        string $resourceClass,
        string $formClass,
        private readonly SlugGeneratorInterface $slugGenerator,
    ) {
        $this->initialize($repository, $formFactory, $resourceClass, $formClass);
    }

    /**
     * Fills the slug of the translation in the locale from its name
     */
    #[LiveAction]
    public function generateTermsSlug(#[LiveArg] string $localeCode): void
    {
        /** @var array<string, array<string, mixed>> $translations */
        $translations = $this->formValues['translations'] ?? [];
        $name = $translations[$localeCode]['name'] ?? '';

        $translations[$localeCode]['slug'] = $this->slugGenerator->generate(is_string($name) ? $name : '', $localeCode);

        $this->formValues['translations'] = $translations;
    }
}
