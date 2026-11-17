<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit\Form\Extension;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusTermsPlugin\Form\Extension\FormTypeExtension;
use Setono\SyliusTermsPlugin\Form\Type\TermsCheckboxCollectionType;
use Setono\SyliusTermsPlugin\Lookup\FormTermsLookupInterface;
use Setono\SyliusTermsPlugin\Model\Terms;
use Setono\SyliusTermsPlugin\Model\TermsVersion;
use Setono\SyliusTermsPlugin\Model\TermsVersionTranslation;
use Setono\SyliusTermsPlugin\Renderer\LabelRendererInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Model\Channel;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;

final class FormTypeExtensionTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<FormTermsLookupInterface> */
    private ObjectProphecy $formTermsLookup;

    private Channel $channel;

    protected function setUp(): void
    {
        $this->formTermsLookup = $this->prophesize(FormTermsLookupInterface::class);
        $this->channel = new Channel();
    }

    #[Test]
    public function it_adds_the_terms_the_lookup_finds_in_the_current_channel_and_locale_to_the_form_whose_type_is_built(): void
    {
        $translation = self::createTranslation('terms_and_conditions');

        $this->formTermsLookup->lookup('newsletter', $this->channel, 'de_DE')->willReturn([$translation]);

        $form = $this->createFormFactory([
            'contact' => ['type' => TextType::class],
            'newsletter' => ['type' => FormType::class],
        ])->create(FormType::class);

        self::assertTrue($form->has('terms'));

        $config = $form->get('terms')->getConfig();
        self::assertInstanceOf(TermsCheckboxCollectionType::class, $config->getType()->getInnerType());
        self::assertSame('newsletter', $config->getOption('form'));
        self::assertSame([$translation], $config->getOption('terms'));
        self::assertTrue($form->get('terms')->has('terms_and_conditions'));
    }

    #[Test]
    public function it_adds_nothing_to_a_form_whose_type_is_not_a_configured_form(): void
    {
        $this->formTermsLookup->lookup(Argument::cetera())->shouldNotBeCalled();

        $form = $this->createFormFactory([
            'contact' => ['type' => TextType::class],
        ])->create(FormType::class);

        self::assertFalse($form->has('terms'));
    }

    #[Test]
    public function it_adds_nothing_when_the_form_has_no_terms(): void
    {
        $this->formTermsLookup->lookup('newsletter', $this->channel, 'de_DE')->willReturn([]);

        $form = $this->createFormFactory([
            'newsletter' => ['type' => FormType::class],
        ])->create(FormType::class);

        self::assertFalse($form->has('terms'));
    }

    /**
     * @param array<string, array{type: class-string}> $forms
     */
    private function createFormFactory(array $forms): FormFactoryInterface
    {
        $labelRenderer = $this->prophesize(LabelRendererInterface::class);
        $labelRenderer->render(Argument::any())->willReturn('I accept the terms');

        $channelContext = $this->prophesize(ChannelContextInterface::class);
        $channelContext->getChannel()->willReturn($this->channel);

        $localeContext = $this->prophesize(LocaleContextInterface::class);
        $localeContext->getLocaleCode()->willReturn('de_DE');

        return Forms::createFormFactoryBuilder()
            // The terms field carries a constraint
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->addType(new TermsCheckboxCollectionType($labelRenderer->reveal()))
            ->addTypeExtension(new FormTypeExtension(
                $this->formTermsLookup->reveal(),
                $channelContext->reveal(),
                $localeContext->reveal(),
                $forms,
            ))
            ->getFormFactory()
        ;
    }

    private static function createTranslation(string $code): TermsVersionTranslation
    {
        $terms = new Terms();
        $terms->setCode($code);

        $version = new TermsVersion();
        $terms->addVersion($version);

        $translation = new TermsVersionTranslation();
        $translation->setLocale('de_DE');
        $version->addTranslation($translation);

        return $translation;
    }
}
