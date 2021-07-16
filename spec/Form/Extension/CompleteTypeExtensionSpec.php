<?php

declare(strict_types=1);

namespace spec\Setono\SyliusTermsPlugin\Form\Extension;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Setono\SyliusTermsPlugin\Form\Extension\CompleteTypeExtension;
use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Setono\SyliusTermsPlugin\Provider\TermsProviderInterface;
use Symfony\Component\Form\FormBuilderInterface;

class CompleteTypeExtensionSpec extends ObjectBehavior
{
    private const OPTIONS = [
        'validation_groups' => ['sylius_checkout_complete']
    ];

    public function let(TermsProviderInterface $termsProvider, TermsInterface $terms): void
    {
        $termsProvider->getTerms()->willReturn([$terms]);

        $this->beConstructedWith($termsProvider);
    }

    public function it_is_initializable(): void
    {
        $this->shouldHaveType(CompleteTypeExtension::class);
    }

    public function it_does_not_add_when_no_terms_exist(TermsProviderInterface $termsProvider, FormBuilderInterface $builder): void
    {
        $termsProvider->getTerms()->willReturn([]);
        $builder->add(Argument::cetera())->shouldNotBeCalled();
        $this->buildForm($builder, self::OPTIONS);
    }

    public function it_adds(FormBuilderInterface $builder): void
    {
        $builder->add(Argument::cetera())->shouldBeCalled();
        $this->buildForm($builder, self::OPTIONS);
    }
}
