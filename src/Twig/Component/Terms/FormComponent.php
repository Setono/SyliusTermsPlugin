<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Twig\Component\Terms;

use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Sylius\Bundle\UiBundle\Twig\Component\ResourceFormComponentTrait;
use Sylius\Bundle\UiBundle\Twig\Component\TemplatePropTrait;
use Sylius\Component\Product\Generator\SlugGeneratorInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;

#[AsLiveComponent]
class FormComponent
{
    /** @use ResourceFormComponentTrait<TermsInterface> */
    use ResourceFormComponentTrait;
    use TemplatePropTrait;

    /**
     * @param RepositoryInterface<TermsInterface> $termsRepository
     */
    public function __construct(
        RepositoryInterface $termsRepository,
        FormFactoryInterface $formFactory,
        string $resourceClass,
        string $formClass,
        protected readonly SlugGeneratorInterface $slugGenerator,
    ) {
        $this->initialize($termsRepository, $formFactory, $resourceClass, $formClass);
    }

    #[LiveAction]
    public function generateTermsSlug(#[LiveArg] string $localeCode): void
    {
        $this->formValues['translations'][$localeCode]['slug'] =
            $this->slugGenerator->generate($this->formValues['translations'][$localeCode]['name'] ?? '');
    }
}
