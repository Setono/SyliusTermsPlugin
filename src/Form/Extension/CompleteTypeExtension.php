<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Form\Extension;

use Setono\SyliusTermsPlugin\Provider\TermsProviderInterface;
use Sylius\Bundle\CoreBundle\Form\Type\Checkout\CompleteType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\NotNull;

final class CompleteTypeExtension extends AbstractTypeExtension
{
    /**
     * @var TermsProviderInterface
     */
    private $termsProvider;

    /**
     * @var array
     */
    private $checkoutCompleteValidationGroups;

    public function __construct(TermsProviderInterface $termsProvider, array $checkoutCompleteValidationGroups)
    {
        $this->termsProvider = $termsProvider;
        $this->checkoutCompleteValidationGroups = $checkoutCompleteValidationGroups;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $terms = $this->termsProvider->getTerms();
        if (0 === count($terms)) {
            return;
        }

        foreach ($terms as $term) {
            $builder->add('terms' . $term->getId(), CheckboxType::class, [
                'label' => false,
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new NotNull([
                        'groups' => $this->checkoutCompleteValidationGroups,
                    ]),
                    new IsTrue([
                        'groups' => $this->checkoutCompleteValidationGroups,
                    ]),
                ],
            ]);
        }
    }

    public static function getExtendedTypes(): iterable
    {
        return [CompleteType::class];
    }
}
