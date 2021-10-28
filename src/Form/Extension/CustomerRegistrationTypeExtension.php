<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Form\Extension;

use Setono\SyliusTermsPlugin\Form\Type\TermsAcceptCollectionType;
use Setono\SyliusTermsPlugin\Provider\TermsProviderInterface;
use Sylius\Bundle\CoreBundle\Form\Type\Customer\CustomerRegistrationType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;

final class CustomerRegistrationTypeExtension extends AbstractTypeExtension
{
    /** @var TermsProviderInterface */
    private $termsProvider;

    public function __construct(TermsProviderInterface $termsProvider)
    {
        $this->termsProvider = $termsProvider;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $terms = $this->termsProvider->getEnabledTermsForCustomerRegistrationForm();
        if (0 === count($terms)) {
            return;
        }

        $builder
            ->add('terms', TermsAcceptCollectionType::class, [
                'terms' => $terms,
                'label' => 'setono_sylius_terms.form.terms_accept.label_registration_form',
            ])
        ;
    }

    public static function getExtendedTypes(): iterable
    {
        return [CustomerRegistrationType::class];
    }

}
