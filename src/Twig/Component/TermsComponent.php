<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Twig\Component;

use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Sylius\Bundle\UiBundle\Twig\Component\LiveCollectionTrait;
use Sylius\Bundle\UiBundle\Twig\Component\ResourceFormComponentTrait;
use Sylius\Bundle\UiBundle\Twig\Component\TemplatePropTrait;
use Sylius\Component\Product\Generator\SlugGeneratorInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\ComponentToolsTrait;

#[AsLiveComponent]
class TermsComponent
{
    use ComponentToolsTrait;
    use LiveCollectionTrait;
    use TemplatePropTrait;

    /** @use ResourceFormComponentTrait<TermsInterface> */
    use ResourceFormComponentTrait;

    /**
     * @param RepositoryInterface<TermsInterface> $productRepository
     */
    public function __construct(
        RepositoryInterface $productRepository,
        FormFactoryInterface $formFactory,
        string $resourceClass,
        string $formClass,
        protected readonly SlugGeneratorInterface $slugGenerator,
    ) {
        $this->initialize($productRepository, $formFactory, $resourceClass, $formClass);
    }

    #[LiveAction]
    public function generateTermsSlug(#[LiveArg] string $localeCode = ''): void
    {
        $this->formValues['translations'][$localeCode]['slug'] = $this->slugGenerator->generate((string) $this->formValues['translations'][$localeCode]['name']);
    }

    protected function getDataModelValue(): string
    {
        return 'norender|*';
    }
}
