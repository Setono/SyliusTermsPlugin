<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit\Model;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusTermsPlugin\Model\Terms;
use Setono\SyliusTermsPlugin\Model\TermsTranslation;
use Setono\SyliusTermsPlugin\Model\TermsTranslationInterface;
use Sylius\Component\Channel\Model\Channel;
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
    public function its_name_slug_label_and_content_are_those_of_its_current_translation(): void
    {
        $terms = new Terms();
        $terms->setCurrentLocale('da_DK');
        $terms->setFallbackLocale('en_US');

        $terms->setName('Handelsbetingelser');
        $terms->setSlug('handelsbetingelser');
        $terms->setLabel('Jeg accepterer [link:handelsbetingelserne]');
        $terms->setContent('## Betaling');

        self::assertSame('Handelsbetingelser', $terms->getName());
        self::assertSame('handelsbetingelser', $terms->getSlug());
        self::assertSame('Jeg accepterer [link:handelsbetingelserne]', $terms->getLabel());
        self::assertSame('## Betaling', $terms->getContent());

        $translation = $terms->getTranslation('da_DK');
        self::assertSame('Handelsbetingelser', $translation->getName());
        self::assertSame('handelsbetingelser', $translation->getSlug());
        self::assertSame('Jeg accepterer [link:handelsbetingelserne]', $translation->getLabel());
        self::assertSame('## Betaling', $translation->getContent());
    }

    #[Test]
    public function it_is_shown_in_the_channels_it_is_added_to(): void
    {
        $webChannel = new Channel();
        $mobileChannel = new Channel();

        $terms = new Terms();
        $terms->addChannel($webChannel);
        $terms->addChannel($webChannel);
        $terms->addChannel($mobileChannel);

        self::assertSame([$webChannel, $mobileChannel], $terms->getChannels()->toArray());

        $terms->removeChannel($webChannel);
        $terms->removeChannel($webChannel);

        self::assertFalse($terms->hasChannel($webChannel));
        self::assertTrue($terms->hasChannel($mobileChannel));
        self::assertSame([$mobileChannel], array_values($terms->getChannels()->toArray()));
    }

    #[Test]
    public function it_is_placed_on_each_form_once(): void
    {
        $terms = new Terms();
        self::assertSame([], $terms->getForms());

        $terms->addForm('registration');
        $terms->addForm('contact');
        $terms->addForm('registration');

        self::assertSame(['registration', 'contact'], $terms->getForms());

        $terms->removeForm('registration');
        self::assertSame(['contact'], $terms->getForms());

        $terms->removeForm('contact');
        self::assertSame([], $terms->getForms());

        $terms->removeForm('contact');
        self::assertSame([], $terms->getForms());
    }

    #[Test]
    public function setting_its_forms_replaces_them_and_drops_duplicates(): void
    {
        $terms = new Terms();
        $terms->setForms(['registration']);
        $terms->setForms(['contact', 'checkout_complete', 'contact']);

        self::assertSame(['contact', 'checkout_complete'], $terms->getForms());
    }

    #[Test]
    public function an_extending_model_can_create_its_own_translations(): void
    {
        $terms = new class() extends Terms {
            protected function createTranslation(): TermsTranslationInterface
            {
                $translation = new TermsTranslation();
                $translation->setLabel('I accept');

                return $translation;
            }
        };
        $terms->setFallbackLocale('en_US');

        self::assertSame('I accept', $terms->getTranslation('en_US')->getLabel());
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
