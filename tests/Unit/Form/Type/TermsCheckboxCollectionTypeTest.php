<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit\Form\Type;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusTermsPlugin\Checker\RequiredTermsCheckerInterface;
use Setono\SyliusTermsPlugin\Form\Type\TermsCheckboxCollectionType;
use Setono\SyliusTermsPlugin\Model\Terms;
use Setono\SyliusTermsPlugin\Model\TermsVersion;
use Setono\SyliusTermsPlugin\Model\TermsVersionTranslation;
use Setono\SyliusTermsPlugin\Model\TermsVersionTranslationInterface;
use Setono\SyliusTermsPlugin\Renderer\LabelRendererInterface;
use Setono\SyliusTermsPlugin\Validator\Constraints\RequiredTermsTicked;
use Setono\SyliusTermsPlugin\Validator\Constraints\RequiredTermsTickedValidator;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Model\Channel;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Validation;

final class TermsCheckboxCollectionTypeTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<RequiredTermsCheckerInterface> */
    private ObjectProphecy $requiredTermsChecker;

    private Channel $channel;

    protected function setUp(): void
    {
        $this->requiredTermsChecker = $this->prophesize(RequiredTermsCheckerInterface::class);
        $this->requiredTermsChecker->getUntickedRequiredTerms(Argument::cetera())->willReturn([]);
        $this->channel = new Channel();
    }

    #[Test]
    public function it_adds_a_checkbox_per_terms_named_by_its_code_and_labelled_with_the_label_of_the_version(): void
    {
        $form = $this->createForm([
            self::createTranslation('terms_and_conditions', required: true),
            self::createTranslation('newsletter', required: false),
        ]);

        self::assertSame(['terms_and_conditions', 'newsletter'], array_keys($form->all()));

        $checkbox = $form->get('terms_and_conditions')->getConfig();
        self::assertInstanceOf(CheckboxType::class, $checkbox->getType()->getInnerType());
        self::assertSame('Rendered: I accept terms_and_conditions', $checkbox->getOption('label'));
        self::assertTrue($checkbox->getOption('label_html'));
        self::assertFalse($checkbox->getOption('translation_domain'), 'The rendered label is no translation key');
    }

    #[Test]
    public function required_terms_render_required_and_optional_terms_do_not(): void
    {
        $view = $this->createForm([
            self::createTranslation('terms_and_conditions', required: true),
            self::createTranslation('newsletter', required: false),
        ])->createView();

        self::assertTrue($view['terms_and_conditions']->vars['required']);
        self::assertFalse($view['newsletter']->vars['required']);
    }

    #[Test]
    public function the_field_has_no_label_and_isnt_mapped(): void
    {
        $config = $this->createForm([self::createTranslation('terms_and_conditions')])->getConfig();

        self::assertFalse($config->getOption('label'));
        self::assertFalse($config->getMapped());
    }

    #[Test]
    public function its_data_is_the_list_of_the_ticked_codes(): void
    {
        $form = $this->createForm([
            self::createTranslation('terms_and_conditions'),
            self::createTranslation('newsletter', required: false),
            self::createTranslation('123', required: false),
        ]);

        self::assertNull($form->getData());
        self::assertFalse($form->get('terms_and_conditions')->getData());

        $form->submit(['newsletter' => '1', '123' => '1']);

        self::assertSame(['newsletter', '123'], $form->getData(), 'A numeric code is a string too');
    }

    #[Test]
    public function its_data_ticks_the_given_codes(): void
    {
        $form = $this->createForm([self::createTranslation('terms_and_conditions'), self::createTranslation('newsletter')]);
        $form->setData(['newsletter']);

        self::assertFalse($form->get('terms_and_conditions')->getData());
        self::assertTrue($form->get('newsletter')->getData());
    }

    #[Test]
    public function the_required_terms_ticked_constraint_checks_the_form_in_the_fields_own_validation_group(): void
    {
        $config = $this->createForm([self::createTranslation('terms_and_conditions')], 'registration')->getConfig();

        self::assertSame([TermsCheckboxCollectionType::VALIDATION_GROUP], $config->getOption('validation_groups'));

        /** @var list<mixed> $constraints */
        $constraints = $config->getOption('constraints');
        self::assertCount(1, $constraints);
        self::assertInstanceOf(RequiredTermsTicked::class, $constraints[0]);
        self::assertSame('registration', $constraints[0]->form);
        self::assertSame([TermsCheckboxCollectionType::VALIDATION_GROUP], $constraints[0]->groups);
    }

    /**
     * The parent's validation groups don't matter: the field validates in its own. With null groups, the 2.x
     * validation-group collector made a constraint that never ran (#80)
     *
     * @param list<string>|null $parentGroups
     */
    #[Test]
    #[TestWith([null])]
    #[TestWith([['sylius']])]
    #[TestWith([['Default']])]
    public function an_unticked_required_terms_puts_the_error_on_its_checkbox_whatever_the_parents_groups(?array $parentGroups): void
    {
        $termsAndConditions = self::createTranslation('terms_and_conditions');

        $this->requiredTermsChecker->getUntickedRequiredTerms('checkout_complete', $this->channel, 'da_DK', ['newsletter'])->willReturn([$termsAndConditions]);

        $form = $this->createFormFactory()
            ->createBuilder(FormType::class, null, ['validation_groups' => $parentGroups])
            ->add('terms', TermsCheckboxCollectionType::class, [
                'form' => 'checkout_complete',
                'terms' => [$termsAndConditions, self::createTranslation('newsletter', required: false)],
            ])
            ->getForm()
        ;

        $form->submit(['terms' => ['newsletter' => '1']]);

        self::assertFalse($form->isValid());
        self::assertCount(0, $form->getErrors());
        self::assertCount(0, $form->get('terms')->getErrors());
        self::assertCount(0, $form->get('terms')->get('newsletter')->getErrors());

        $errors = $form->get('terms')->get('terms_and_conditions')->getErrors();
        self::assertCount(1, $errors);
        self::assertSame('setono_sylius_terms.terms_checkbox.required', $errors[0]->getMessage());
    }

    #[Test]
    public function it_is_valid_when_every_required_terms_is_ticked(): void
    {
        $this->requiredTermsChecker->getUntickedRequiredTerms('checkout_complete', $this->channel, 'da_DK', ['terms_and_conditions'])->willReturn([]);

        $form = $this->createForm([self::createTranslation('terms_and_conditions')]);
        $form->submit(['terms_and_conditions' => '1']);

        self::assertTrue($form->isValid());
    }

    #[Test]
    public function it_has_a_block_prefix_of_its_own(): void
    {
        self::assertSame(
            'setono_sylius_terms_terms_checkbox_collection',
            (new TermsCheckboxCollectionType($this->prophesize(LabelRendererInterface::class)->reveal()))->getBlockPrefix(),
        );
    }

    /**
     * @param list<TermsVersionTranslationInterface> $terms
     */
    private function createForm(array $terms, string $form = 'checkout_complete'): FormInterface
    {
        return $this->createFormFactory()->create(TermsCheckboxCollectionType::class, null, [
            'form' => $form,
            'terms' => $terms,
        ]);
    }

    private function createFormFactory(): FormFactoryInterface
    {
        $labelRenderer = $this->prophesize(LabelRendererInterface::class);
        $labelRenderer->render(Argument::type(TermsVersionTranslationInterface::class))->will(static function (array $arguments): string {
            $translation = $arguments[0];
            \assert($translation instanceof TermsVersionTranslationInterface);

            return 'Rendered: ' . (string) $translation->getLabel();
        });

        $channelContext = $this->prophesize(ChannelContextInterface::class);
        $channelContext->getChannel()->willReturn($this->channel);

        $localeContext = $this->prophesize(LocaleContextInterface::class);
        $localeContext->getLocaleCode()->willReturn('da_DK');

        $validator = Validation::createValidatorBuilder()
            ->setConstraintValidatorFactory(new ConstraintValidatorFactory([
                RequiredTermsTickedValidator::class => new RequiredTermsTickedValidator(
                    $this->requiredTermsChecker->reveal(),
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

    private static function createTranslation(string $code, bool $required = true): TermsVersionTranslation
    {
        $terms = new Terms();
        $terms->setCode($code);
        $terms->setRequired($required);

        $version = new TermsVersion();
        $terms->addVersion($version);

        $translation = new TermsVersionTranslation();
        $translation->setLocale('da_DK');
        $translation->setLabel('I accept ' . $code);
        $version->addTranslation($translation);

        return $translation;
    }
}
