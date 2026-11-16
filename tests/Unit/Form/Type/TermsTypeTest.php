<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit\Form\Type;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusTermsPlugin\Form\Type\TermsType;
use Setono\SyliusTermsPlugin\Model\Terms;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

final class TermsTypeTest extends TestCase
{
    use ProphecyTrait;

    #[Test]
    public function the_forms_field_offers_the_form_codes_by_their_label(): void
    {
        $builder = $this->prophesize(FormBuilderInterface::class);
        $builder->add(Argument::cetera())->willReturn($builder);
        $builder->addEventSubscriber(Argument::any())->willReturn($builder);
        $builder
            ->add('forms', ChoiceType::class, Argument::withEntry('choices', [
                'setono_sylius_terms.form.terms.term_form.checkout_complete' => 'checkout_complete',
                'app.form.newsletter' => 'newsletter',
            ]))
            ->willReturn($builder)
            ->shouldBeCalledOnce()
        ;

        $type = new TermsType(Terms::class, [
            'checkout_complete' => ['label' => 'setono_sylius_terms.form.terms.term_form.checkout_complete'],
            'newsletter' => ['label' => 'app.form.newsletter'],
        ]);

        $type->buildForm($builder->reveal(), []);
    }
}
