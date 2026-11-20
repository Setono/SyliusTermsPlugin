<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit\Twig\Component;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusTermsPlugin\Form\Type\TermsType;
use Setono\SyliusTermsPlugin\Generator\SlugGeneratorInterface;
use Setono\SyliusTermsPlugin\Model\Terms;
use Setono\SyliusTermsPlugin\Twig\Component\TermsFormComponent;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\Form\FormFactoryInterface;

final class TermsFormComponentTest extends TestCase
{
    use ProphecyTrait;

    #[Test]
    public function it_fills_the_slug_of_the_locale_from_its_name(): void
    {
        $slugGenerator = $this->prophesize(SlugGeneratorInterface::class);
        $slugGenerator->generate('Datenschutzerklärung', 'de_DE')->willReturn('datenschutzerklaerung');

        $component = $this->createComponent($slugGenerator->reveal());
        $component->formValues = ['translations' => [
            'en_US' => ['name' => 'Privacy Policy', 'slug' => 'privacy'],
            'de_DE' => ['name' => 'Datenschutzerklärung', 'slug' => ''],
        ]];

        $component->generateTermsSlug('de_DE');

        self::assertSame(['translations' => [
            'en_US' => ['name' => 'Privacy Policy', 'slug' => 'privacy'],
            'de_DE' => ['name' => 'Datenschutzerklärung', 'slug' => 'datenschutzerklaerung'],
        ]], $component->formValues);
    }

    #[Test]
    public function it_fills_an_empty_slug_when_the_locale_has_no_name(): void
    {
        $slugGenerator = $this->prophesize(SlugGeneratorInterface::class);
        $slugGenerator->generate('', 'de_DE')->willReturn('');

        $component = $this->createComponent($slugGenerator->reveal());
        $component->formValues = [];

        $component->generateTermsSlug('de_DE');

        self::assertSame(['translations' => ['de_DE' => ['slug' => '']]], $component->formValues);
    }

    #[Test]
    public function it_hydrates_the_terms_by_its_id_and_a_new_terms_from_nothing(): void
    {
        $terms = new Terms();

        $repository = $this->prophesize(RepositoryInterface::class);
        $repository->find(7)->willReturn($terms);

        $component = new TermsFormComponent(
            $repository->reveal(),
            $this->prophesize(FormFactoryInterface::class)->reveal(),
            Terms::class,
            TermsType::class,
            $this->prophesize(SlugGeneratorInterface::class)->reveal(),
        );

        self::assertSame($terms, $component->hydrateResource(7));
        self::assertInstanceOf(Terms::class, $component->hydrateResource(null));
        self::assertNotSame($terms, $component->hydrateResource(null));
    }

    private function createComponent(SlugGeneratorInterface $slugGenerator): TermsFormComponent
    {
        return new TermsFormComponent(
            $this->prophesize(RepositoryInterface::class)->reveal(),
            $this->prophesize(FormFactoryInterface::class)->reveal(),
            Terms::class,
            TermsType::class,
            $slugGenerator,
        );
    }
}
