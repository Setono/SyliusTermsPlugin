<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit\Model;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusTermsPlugin\Model\Terms;
use Setono\SyliusTermsPlugin\Model\TermsTranslation;
use Setono\SyliusTermsPlugin\Model\TermsTranslationInterface;
use Sylius\Resource\Model\TranslationInterface;

final class TermsTest extends TestCase
{
    use ProphecyTrait;

    #[Test]
    public function a_terms_is_required_by_default(): void
    {
        $terms = new Terms();
        self::assertTrue($terms->isRequired());

        $terms->setRequired(false);
        self::assertFalse($terms->isRequired());
    }

    #[Test]
    public function a_new_terms_has_no_position(): void
    {
        self::assertNull((new Terms())->getPosition());
    }

    #[Test]
    public function it_keeps_the_position_it_is_given(): void
    {
        $terms = new Terms();
        $terms->setPosition(3);

        self::assertSame(3, $terms->getPosition());
    }

    #[Test]
    public function a_null_position_is_stored_as_gedmos_last_position(): void
    {
        $terms = new Terms();
        $terms->setPosition(3);
        $terms->setPosition(null);

        self::assertSame(-1, $terms->getPosition());
    }

    #[Test]
    public function its_translations_are_terms_translations(): void
    {
        $terms = new Terms();
        $terms->setFallbackLocale('en_US');

        $translation = $terms->getTranslation('en_US');

        self::assertInstanceOf(TermsTranslation::class, $translation);
        self::assertSame($terms, $translation->getTranslatable());
    }

    #[Test]
    public function it_throws_when_its_translation_is_not_a_terms_translation(): void
    {
        $terms = new Terms();

        $translation = $this->prophesize(TranslationInterface::class);
        $translation->getLocale()->willReturn('en_US');
        $translation->setTranslatable($terms)->shouldBeCalled();
        $terms->addTranslation($translation->reveal());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(TermsTranslationInterface::class);

        $terms->getTranslation('en_US');
    }
}
