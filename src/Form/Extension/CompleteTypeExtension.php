<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Form\Extension;

use Setono\SyliusTermsPlugin\Form\Type\TermsAcceptCollectionType;
use Setono\SyliusTermsPlugin\Provider\TermsProviderInterface;
use Sylius\Bundle\CoreBundle\Form\Type\Checkout\CompleteType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;

final class CompleteTypeExtension extends AbstractTypeExtension
{
    /**
     * @var TermsProviderInterface
     */
    private $termsProvider;

    /**
     * Not sure about this
     * @todo Remove?
     *
     * @var array
     */
    private $checkoutCompleteValidationGroups;

    /**
     * @param TermsProviderInterface $termsProvider
     * @param array $checkoutCompleteValidationGroups
     */
    public function __construct(TermsProviderInterface $termsProvider, array $checkoutCompleteValidationGroups)
    {
        $this->termsProvider = $termsProvider;
        $this->checkoutCompleteValidationGroups = $checkoutCompleteValidationGroups;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $terms = $this->termsProvider->getTerms();
        if (0 === count($terms)) {
            return;
        }

        $builder
            ->add('terms', TermsAcceptCollectionType::class, [
                'terms' => $terms,
            ])
        ;
    }

    /**
     * {@inheritdoc}
     */
    public static function getExtendedTypes(): iterable
    {
        return [CompleteType::class];
    }
}
