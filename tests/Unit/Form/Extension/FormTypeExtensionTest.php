<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit\Form\Extension;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusTermsPlugin\Dispatcher\TermsAnsweredDispatcherInterface;
use Setono\SyliusTermsPlugin\Form\EventSubscriber\DispatchTermsAnsweredSubscriber;
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
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Validation;

final class FormTypeExtensionTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<FormTermsLookupInterface> */
    private ObjectProphecy $formTermsLookup;

    private Channel $channel;

    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $this->formTermsLookup = $this->prophesize(FormTermsLookupInterface::class);
        $this->channel = new Channel();
        $this->requestStack = new RequestStack();
    }

    #[Test]
    public function it_adds_the_terms_the_lookup_finds_in_the_current_channel_and_locale_to_the_form_whose_type_is_built(): void
    {
        $translation = self::createTranslation('terms_and_conditions');

        $this->formTermsLookup->lookup('newsletter', $this->channel, 'de_DE')->willReturn([$translation]);

        $form = $this->createFormFactory([
            'contact' => ['type' => TextType::class, 'saved_event' => null],
            'newsletter' => ['type' => FormType::class, 'saved_event' => null],
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
            'contact' => ['type' => TextType::class, 'saved_event' => null],
        ])->create(FormType::class);

        self::assertFalse($form->has('terms'));
        self::assertFalse(self::dispatchesAnswers($form));
    }

    #[Test]
    public function it_adds_nothing_when_the_form_has_no_terms(): void
    {
        $this->formTermsLookup->lookup('newsletter', $this->channel, 'de_DE')->willReturn([]);

        $form = $this->createFormFactory([
            'newsletter' => ['type' => FormType::class, 'saved_event' => null],
        ])->create(FormType::class);

        self::assertFalse($form->has('terms'));
        self::assertFalse(self::dispatchesAnswers($form), 'A Form with no Terms has no Answers');
    }

    #[Test]
    public function it_hands_the_answers_of_a_valid_submit_to_the_dispatcher_after_validation(): void
    {
        $this->formTermsLookup->lookup('newsletter', $this->channel, 'de_DE')->willReturn([self::createTranslation('terms_and_conditions')]);

        $form = $this->createFormFactory([
            'newsletter' => ['type' => FormType::class, 'saved_event' => null],
        ])->create(FormType::class);

        self::assertTrue(self::dispatchesAnswers($form));
    }

    #[Test]
    public function in_a_live_component_request_a_form_without_a_saved_event_dispatches_nothing(): void
    {
        $this->formTermsLookup->lookup('newsletter', $this->channel, 'de_DE')->willReturn([self::createTranslation('terms_and_conditions')]);
        $this->requestStack->push(new Request(attributes: ['_live_component' => 'app:newsletter_form']));

        $form = $this->createFormFactory([
            'newsletter' => ['type' => FormType::class, 'saved_event' => null],
        ])->create(FormType::class);

        self::assertTrue($form->has('terms'), 'The checkboxes still render');
        self::assertFalse(self::dispatchesAnswers($form));
    }

    #[Test]
    public function in_a_live_component_request_a_form_with_a_saved_event_hands_its_answers_to_the_dispatcher_which_holds_them(): void
    {
        $this->formTermsLookup->lookup('newsletter', $this->channel, 'de_DE')->willReturn([self::createTranslation('terms_and_conditions')]);
        $this->requestStack->push(new Request(attributes: ['_live_component' => 'app:newsletter_form']));

        $form = $this->createFormFactory([
            'newsletter' => ['type' => FormType::class, 'saved_event' => 'app.newsletter.post_subscribe'],
        ])->create(FormType::class);

        self::assertTrue(self::dispatchesAnswers($form));
    }

    /**
     * Whether the form hands its Answers to the dispatcher on POST_SUBMIT, after Symfony's ValidationListener at 0
     */
    private static function dispatchesAnswers(FormInterface $form): bool
    {
        $dispatcher = $form->getConfig()->getEventDispatcher();

        foreach ($dispatcher->getListeners(FormEvents::POST_SUBMIT) as $listener) {
            if (is_array($listener) && is_callable($listener) && $listener[0] instanceof DispatchTermsAnsweredSubscriber) {
                self::assertLessThan(0, $dispatcher->getListenerPriority(FormEvents::POST_SUBMIT, $listener));

                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, array{type: class-string, saved_event: string|null}> $forms
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
                $this->prophesize(TermsAnsweredDispatcherInterface::class)->reveal(),
                $this->requestStack,
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
