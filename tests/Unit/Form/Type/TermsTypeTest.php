<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit\Form\Type;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusTermsPlugin\Form\Type\TermsTranslationType;
use Setono\SyliusTermsPlugin\Form\Type\TermsType;
use Setono\SyliusTermsPlugin\Generator\SlugGeneratorInterface;
use Setono\SyliusTermsPlugin\Model\Terms;
use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Setono\SyliusTermsPlugin\Model\TermsTranslation;
use Sylius\Bundle\ChannelBundle\Form\Type\ChannelChoiceType;
use Sylius\Bundle\ResourceBundle\Form\Type\ResourceTranslationsType;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Translation\Provider\TranslationLocaleProviderInterface;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;

final class TermsTypeTest extends TestCase
{
    use ProphecyTrait;

    private const FORMS = [
        'checkout_complete' => ['label' => 'setono_sylius_terms.form.terms.term_form.checkout_complete'],
        'newsletter' => ['label' => 'app.form.newsletter'],
    ];

    #[Test]
    public function the_forms_field_offers_the_configured_forms_by_their_label(): void
    {
        $checkboxes = self::getFormsCheckboxes($this->createForm(new Terms()));

        self::assertSame([
            'checkout_complete' => ['label' => 'setono_sylius_terms.form.terms.term_form.checkout_complete', 'parameters' => [], 'ticked' => false],
            'newsletter' => ['label' => 'app.form.newsletter', 'parameters' => [], 'ticked' => false],
        ], $checkboxes);
    }

    #[Test]
    public function a_stored_form_code_that_is_not_configured_is_offered_ticked_and_flagged(): void
    {
        $checkboxes = self::getFormsCheckboxes($this->createForm(self::createTerms(['newsletter', 'legacy_form'])));

        self::assertSame([
            'checkout_complete' => ['label' => 'setono_sylius_terms.form.terms.term_form.checkout_complete', 'parameters' => [], 'ticked' => false],
            'newsletter' => ['label' => 'app.form.newsletter', 'parameters' => [], 'ticked' => true],
            'legacy_form' => ['label' => 'setono_sylius_terms.form.terms.unconfigured_form', 'parameters' => ['%code%' => 'legacy_form'], 'ticked' => true],
        ], $checkboxes);
    }

    #[Test]
    public function a_stored_form_code_that_is_not_configured_is_kept_on_submit(): void
    {
        $terms = self::createTerms(['newsletter', 'legacy_form']);
        $form = $this->createForm($terms);

        $form->submit(self::getSubmittedData(['checkout_complete', 'legacy_form']));

        self::assertTrue($form->isSynchronized());
        self::assertSame(['legacy_form', 'checkout_complete'], $terms->getForms());
    }

    #[Test]
    public function a_stored_form_code_that_is_not_configured_is_removed_when_unticked(): void
    {
        $terms = self::createTerms(['newsletter', 'legacy_form']);
        $form = $this->createForm($terms);

        $form->submit(self::getSubmittedData(['newsletter']));

        self::assertTrue($form->isSynchronized());
        self::assertSame(['newsletter'], $terms->getForms());

        // Once removed, the code is no longer offered
        self::assertSame(['checkout_complete', 'newsletter'], array_keys(self::getFormsCheckboxes($this->createForm($terms))));
    }

    #[Test]
    public function it_offers_the_general_fields(): void
    {
        $form = $this->createForm(new Terms());

        self::assertTrue($form->has('code'));
        self::assertTrue($form->has('enabled'));
        self::assertTrue($form->has('required'));
        self::assertTrue($form->has('position'));
        self::assertTrue($form->has('channels'));
        self::assertTrue($form->has('translations'));
        self::assertTrue($form->getConfig()->getType()->getInnerType() instanceof TermsType);
        self::assertSame('setono_sylius_terms_terms', $form->getConfig()->getType()->getBlockPrefix());
    }

    #[Test]
    public function required_is_ticked_for_new_terms(): void
    {
        self::assertTrue($this->createForm(new Terms())->get('required')->getData());
    }

    #[Test]
    public function a_blank_position_puts_the_terms_last(): void
    {
        $terms = self::createTerms([]);
        $terms->setPosition(3);

        $form = $this->createForm($terms);
        $form->submit(self::getSubmittedData([]) + ['required' => '1', 'position' => '']);

        self::assertTrue($form->isSynchronized());
        // -1 is "last" to Gedmo's sortable listener, see Terms::setPosition()
        self::assertSame(-1, $terms->getPosition());
    }

    /**
     * @param list<string> $forms
     */
    private static function createTerms(array $forms): TermsInterface
    {
        $terms = new Terms();
        $terms->setCode('terms');
        $terms->setForms($forms);

        return $terms;
    }

    /**
     * @param list<string> $forms
     *
     * @return array<string, mixed>
     */
    private static function getSubmittedData(array $forms): array
    {
        return [
            'code' => 'terms',
            'enabled' => '1',
            'forms' => $forms,
            'translations' => [
                'en_US' => ['name' => 'Terms', 'slug' => 'terms', 'label' => 'I accept the [link:terms]', 'content' => ''],
            ],
        ];
    }

    /**
     * The checkboxes of the Forms field by their value. Read from the forms rather than their views, whose types
     * differ between Symfony 6.4 and 7.4
     *
     * @return array<string, array{label: mixed, parameters: mixed, ticked: mixed}>
     */
    private static function getFormsCheckboxes(FormInterface $form): array
    {
        $checkboxes = [];

        foreach ($form->get('forms') as $checkbox) {
            $config = $checkbox->getConfig();

            $value = $config->getOption('value');
            self::assertIsString($value);

            $checkboxes[$value] = [
                'label' => $config->getOption('label'),
                'parameters' => $config->getOption('label_translation_parameters'),
                'ticked' => $checkbox->getData(),
            ];
        }

        return $checkboxes;
    }

    private function createForm(TermsInterface $terms): FormInterface
    {
        return $this->createFormFactory()->create(TermsType::class, $terms);
    }

    private function createFormFactory(): FormFactoryInterface
    {
        $channelRepository = $this->prophesize(RepositoryInterface::class);
        $channelRepository->findAll()->willReturn([]);

        $localeProvider = $this->prophesize(TranslationLocaleProviderInterface::class);
        $localeProvider->getDefinedLocalesCodes()->willReturn(['en_US']);
        $localeProvider->getDefaultLocaleCode()->willReturn('en_US');

        $slugGenerator = $this->prophesize(SlugGeneratorInterface::class);
        $slugGenerator->generate(Argument::cetera())->willReturn('terms');

        return Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->addTypes([
                new TermsType(Terms::class, self::FORMS),
                new TermsTranslationType(TermsTranslation::class, $slugGenerator->reveal()),
                new ChannelChoiceType($channelRepository->reveal()),
                new ResourceTranslationsType($localeProvider->reveal()),
            ])
            ->getFormFactory()
        ;
    }
}
