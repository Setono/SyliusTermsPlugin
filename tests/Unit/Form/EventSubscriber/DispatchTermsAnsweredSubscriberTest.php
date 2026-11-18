<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit\Form\EventSubscriber;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusTermsPlugin\Checker\RequiredTermsCheckerInterface;
use Setono\SyliusTermsPlugin\Dispatcher\TermsAnsweredDispatcherInterface;
use Setono\SyliusTermsPlugin\Event\Answer;
use Setono\SyliusTermsPlugin\Event\TermsAnsweredEvent;
use Setono\SyliusTermsPlugin\Form\EventSubscriber\DispatchTermsAnsweredSubscriber;
use Setono\SyliusTermsPlugin\Form\Type\TermsCheckboxCollectionType;
use Setono\SyliusTermsPlugin\Model\Terms;
use Setono\SyliusTermsPlugin\Model\TermsVersion;
use Setono\SyliusTermsPlugin\Model\TermsVersionTranslation;
use Setono\SyliusTermsPlugin\Renderer\LabelRendererInterface;
use Setono\SyliusTermsPlugin\Validator\Constraints\RequiredTermsTickedValidator;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Model\Channel;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Validation;

/**
 * A newsletter Form showing a required terms_and_conditions and an optional newsletter_consent
 */
final class DispatchTermsAnsweredSubscriberTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<TermsAnsweredDispatcherInterface> */
    private ObjectProphecy $termsAnsweredDispatcher;

    private Channel $channel;

    private TermsVersionTranslation $termsAndConditions;

    private TermsVersionTranslation $newsletterConsent;

    private FormFactoryInterface $formFactory;

    protected function setUp(): void
    {
        $this->termsAnsweredDispatcher = $this->prophesize(TermsAnsweredDispatcherInterface::class);
        $this->channel = new Channel();
        $this->termsAndConditions = self::createTranslation('terms_and_conditions', required: true);
        $this->newsletterConsent = self::createTranslation('newsletter_consent', required: false);
        $this->formFactory = $this->createFormFactory();
    }

    #[Test]
    public function it_dispatches_the_answer_to_each_terms_shown_on_a_valid_submit(): void
    {
        $dispatched = [];
        $this->termsAnsweredDispatcher->dispatch(Argument::type(TermsAnsweredEvent::class))->will(static function (array $arguments) use (&$dispatched): void {
            $dispatched[] = $arguments[0];
        });

        $form = $this->createNewsletterForm();
        $form->submit(['email' => 'jane@example.com', 'terms' => ['terms_and_conditions' => '1']]);

        self::assertTrue($form->isValid());
        self::assertCount(1, $dispatched);

        $event = $dispatched[0];
        self::assertInstanceOf(TermsAnsweredEvent::class, $event);
        self::assertSame('newsletter', $event->form);
        self::assertSame(['email' => 'jane@example.com'], $event->data, 'The data is the form\'s, which the unmapped terms field isn\'t part of');
        self::assertSame($this->channel, $event->channel);
        self::assertSame('en_US', $event->localeCode);
        self::assertEquals([
            new Answer($this->termsAndConditions->getVersion(), true),
            new Answer($this->newsletterConsent->getVersion(), false),
        ], $event->answers);
        self::assertSame($this->termsAndConditions->getVersion(), $event->answers[0]->version);
    }

    #[Test]
    public function it_dispatches_nothing_when_another_field_is_invalid(): void
    {
        $this->termsAnsweredDispatcher->dispatch(Argument::any())->shouldNotBeCalled();

        $form = $this->createNewsletterForm();
        $form->submit(['email' => '', 'terms' => ['terms_and_conditions' => '1']]);

        self::assertFalse($form->isValid());
    }

    #[Test]
    public function it_dispatches_nothing_when_a_required_terms_is_left_unticked(): void
    {
        $this->termsAnsweredDispatcher->dispatch(Argument::any())->shouldNotBeCalled();

        $form = $this->createNewsletterForm();
        $form->submit(['email' => 'jane@example.com', 'terms' => ['newsletter_consent' => '1']]);

        self::assertFalse($form->isValid());
    }

    #[Test]
    public function it_dispatches_nothing_for_a_form_nested_in_another_form(): void
    {
        $this->termsAnsweredDispatcher->dispatch(Argument::any())->shouldNotBeCalled();

        $form = $this->formFactory->createBuilder(FormType::class)
            ->add($this->createNewsletterFormBuilder('newsletter'))
            ->getForm()
        ;
        $form->submit(['newsletter' => ['email' => 'jane@example.com', 'terms' => ['terms_and_conditions' => '1']]]);

        self::assertTrue($form->isValid());
    }

    #[Test]
    public function it_dispatches_nothing_for_a_form_without_a_terms_field(): void
    {
        $this->termsAnsweredDispatcher->dispatch(Argument::any())->shouldNotBeCalled();

        $form = $this->formFactory->createBuilder(FormType::class)
            ->addEventSubscriber($this->createSubscriber())
            ->getForm()
        ;
        $form->submit([]);

        self::assertTrue($form->isValid());
    }

    private function createNewsletterForm(): FormInterface
    {
        return $this->createNewsletterFormBuilder()->getForm();
    }

    private function createNewsletterFormBuilder(string $name = 'newsletter_form'): FormBuilderInterface
    {
        return $this->formFactory->createNamedBuilder($name, FormType::class)
            ->add('email', TextType::class, ['constraints' => [new NotBlank()]])
            ->add('terms', TermsCheckboxCollectionType::class, [
                'form' => 'newsletter',
                'terms' => [$this->termsAndConditions, $this->newsletterConsent],
            ])
            ->addEventSubscriber($this->createSubscriber())
        ;
    }

    private function createSubscriber(): DispatchTermsAnsweredSubscriber
    {
        return new DispatchTermsAnsweredSubscriber(
            $this->termsAnsweredDispatcher->reveal(),
            'newsletter',
            $this->channel,
            'en_US',
            [$this->termsAndConditions, $this->newsletterConsent],
        );
    }

    private function createFormFactory(): FormFactoryInterface
    {
        $labelRenderer = $this->prophesize(LabelRendererInterface::class);
        $labelRenderer->render(Argument::any())->willReturn('I accept the terms');

        // Reports terms_and_conditions unless it is ticked. Prophecy binds the callback to the double, hence the variable
        $termsAndConditions = $this->termsAndConditions;
        $requiredTermsChecker = $this->prophesize(RequiredTermsCheckerInterface::class);
        $requiredTermsChecker->getUntickedRequiredTerms('newsletter', $this->channel, 'en_US', Argument::type('array'))->will(
            static function (array $arguments) use ($termsAndConditions): array {
                $tickedTerms = $arguments[3];
                self::assertIsArray($tickedTerms);

                return in_array('terms_and_conditions', $tickedTerms, true) ? [] : [$termsAndConditions];
            },
        );

        $channelContext = $this->prophesize(ChannelContextInterface::class);
        $channelContext->getChannel()->willReturn($this->channel);

        $localeContext = $this->prophesize(LocaleContextInterface::class);
        $localeContext->getLocaleCode()->willReturn('en_US');

        $validator = Validation::createValidatorBuilder()
            ->setConstraintValidatorFactory(new ConstraintValidatorFactory([
                RequiredTermsTickedValidator::class => new RequiredTermsTickedValidator(
                    $requiredTermsChecker->reveal(),
                    $channelContext->reveal(),
                    $localeContext->reveal(),
                ),
            ]))
            ->getValidator()
        ;

        return Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension($validator))
            ->addType(new TermsCheckboxCollectionType($labelRenderer->reveal()))
            ->getFormFactory()
        ;
    }

    private static function createTranslation(string $code, bool $required): TermsVersionTranslation
    {
        $terms = new Terms();
        $terms->setCode($code);
        $terms->setRequired($required);

        $version = new TermsVersion();
        $terms->addVersion($version);

        $translation = new TermsVersionTranslation();
        $translation->setLocale('en_US');
        $translation->setName($code);
        $version->addTranslation($translation);

        return $translation;
    }
}
