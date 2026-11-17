<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit\Form\EventSubscriber;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusTermsPlugin\Form\EventSubscriber\FillBlankSlugSubscriber;
use Setono\SyliusTermsPlugin\Generator\SlugGeneratorInterface;
use Setono\SyliusTermsPlugin\Model\TermsTranslation;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;

final class FillBlankSlugSubscriberTest extends TestCase
{
    use ProphecyTrait;

    #[Test]
    public function it_fills_on_submit_before_validation(): void
    {
        self::assertSame([FormEvents::SUBMIT => 'fill'], FillBlankSlugSubscriber::getSubscribedEvents());
    }

    #[Test]
    #[DataProvider('provideBlankSlugs')]
    public function it_fills_a_blank_slug_from_the_name_in_the_locale_of_the_translation(?string $slug): void
    {
        $slugGenerator = $this->prophesize(SlugGeneratorInterface::class);
        $slugGenerator->generate('Datenschutzerklärung', 'de_DE')->willReturn('datenschutzerklaerung')->shouldBeCalledOnce();

        $translation = self::createTranslation('Datenschutzerklärung', $slug);
        (new FillBlankSlugSubscriber($slugGenerator->reveal()))->fill($this->createEvent($translation));

        self::assertSame('datenschutzerklaerung', $translation->getSlug());
    }

    /**
     * @return \Generator<string, array{string|null}>
     */
    public static function provideBlankSlugs(): \Generator
    {
        yield 'no slug' => [null];
        yield 'empty slug' => [''];
        yield 'whitespace slug' => ['  '];
    }

    #[Test]
    public function it_leaves_a_slug_that_is_not_blank(): void
    {
        $slugGenerator = $this->prophesize(SlugGeneratorInterface::class);
        $slugGenerator->generate(Argument::cetera())->shouldNotBeCalled();

        $translation = self::createTranslation('Datenschutzerklärung', 'datenschutz');
        (new FillBlankSlugSubscriber($slugGenerator->reveal()))->fill($this->createEvent($translation));

        self::assertSame('datenschutz', $translation->getSlug());
    }

    #[Test]
    #[DataProvider('provideBlankNames')]
    public function it_leaves_the_slug_blank_without_a_name(?string $name): void
    {
        $slugGenerator = $this->prophesize(SlugGeneratorInterface::class);
        $slugGenerator->generate(Argument::cetera())->shouldNotBeCalled();

        $translation = self::createTranslation($name, null);
        (new FillBlankSlugSubscriber($slugGenerator->reveal()))->fill($this->createEvent($translation));

        self::assertNull($translation->getSlug());
    }

    /**
     * @return \Generator<string, array{string|null}>
     */
    public static function provideBlankNames(): \Generator
    {
        yield 'no name' => [null];
        yield 'empty name' => [''];
        yield 'whitespace name' => [' '];
    }

    /**
     * A locale left blank has no translation, and stays without one
     */
    #[Test]
    public function it_ignores_a_locale_without_a_translation(): void
    {
        $slugGenerator = $this->prophesize(SlugGeneratorInterface::class);
        $slugGenerator->generate(Argument::cetera())->shouldNotBeCalled();

        $event = $this->createEvent(null);
        (new FillBlankSlugSubscriber($slugGenerator->reveal()))->fill($event);

        self::assertNull($event->getData());
    }

    private static function createTranslation(?string $name, ?string $slug): TermsTranslation
    {
        $translation = new TermsTranslation();
        $translation->setName($name);
        $translation->setSlug($slug);

        return $translation;
    }

    private function createEvent(?TermsTranslation $translation): FormEvent
    {
        $form = $this->prophesize(FormInterface::class);
        $form->getName()->willReturn('de_DE');

        return new FormEvent($form->reveal(), $translation);
    }
}
