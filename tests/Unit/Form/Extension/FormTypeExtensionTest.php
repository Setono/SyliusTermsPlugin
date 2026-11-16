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
use Setono\SyliusTermsPlugin\Model\Terms;
use Setono\SyliusTermsPlugin\Provider\TermsProviderInterface;
use Setono\SyliusTermsPlugin\Renderer\LabelRendererInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;

final class FormTypeExtensionTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<TermsProviderInterface> */
    private ObjectProphecy $termsProvider;

    protected function setUp(): void
    {
        $this->termsProvider = $this->prophesize(TermsProviderInterface::class);
    }

    #[Test]
    public function it_adds_the_terms_of_the_form_whose_type_is_built(): void
    {
        $terms = new Terms();
        $terms->setCode('terms_and_conditions');

        $this->termsProvider->getTerms('newsletter')->willReturn([$terms]);

        $form = $this->createFormFactory([
            'contact' => ['type' => TextType::class],
            'newsletter' => ['type' => FormType::class],
        ])->create(FormType::class);

        self::assertTrue($form->has('terms'));
        self::assertInstanceOf(TermsCheckboxCollectionType::class, $form->get('terms')->getConfig()->getType()->getInnerType());
        self::assertTrue($form->get('terms')->has('terms_and_conditions'));
    }

    #[Test]
    public function it_adds_nothing_to_a_form_whose_type_is_not_a_configured_form(): void
    {
        $this->termsProvider->getTerms(Argument::any())->shouldNotBeCalled();

        $form = $this->createFormFactory([
            'contact' => ['type' => TextType::class],
        ])->create(FormType::class);

        self::assertFalse($form->has('terms'));
    }

    #[Test]
    public function it_adds_nothing_when_the_form_has_no_terms(): void
    {
        $this->termsProvider->getTerms('newsletter')->willReturn([]);

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

        return Forms::createFormFactoryBuilder()
            // The terms checkboxes carry constraints
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->addType(new TermsCheckboxCollectionType($labelRenderer->reveal()))
            ->addTypeExtension(new FormTypeExtension($this->termsProvider->reveal(), $forms))
            ->getFormFactory()
        ;
    }
}
