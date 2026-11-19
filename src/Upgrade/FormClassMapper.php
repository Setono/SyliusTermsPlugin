<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Upgrade;

/**
 * Maps the form type classes a 2.x Terms stores to the codes of 3.0 Forms. Part of the upgrade from 2.x, which 4.0
 * removes
 */
final readonly class FormClassMapper
{
    /**
     * The Sylius 1 form types a 2.x app could place Terms on, with the code of the Form each one becomes. Only the first
     * three are configured by default
     */
    public const SYLIUS_1_FORM_CODES = [
        'Sylius\Bundle\CoreBundle\Form\Type\Checkout\CompleteType' => 'checkout_complete',
        'Sylius\Bundle\CoreBundle\Form\Type\Customer\CustomerRegistrationType' => 'registration',
        'Sylius\Bundle\CoreBundle\Form\Type\ContactType' => 'contact',
        'Sylius\Bundle\CoreBundle\Form\Type\Checkout\AddressType' => 'checkout_address',
        'Sylius\Bundle\CoreBundle\Form\Type\Checkout\SelectShippingType' => 'checkout_select_shipping',
        'Sylius\Bundle\CoreBundle\Form\Type\Checkout\SelectPaymentType' => 'checkout_select_payment',
        'Sylius\Bundle\CoreBundle\Form\Type\Product\ProductReviewType' => 'product_review',
    ];

    /** @var array<string, string> Form codes by lowercased form type class */
    private array $codesByClass;

    /**
     * @param array<string, array{type: string}> $forms the enabled Forms, keyed by code (the setono_sylius_terms.forms parameter)
     */
    public function __construct(private array $forms)
    {
        $codesByClass = [];

        foreach (self::SYLIUS_1_FORM_CODES as $class => $code) {
            $codesByClass[self::normalize($class)] = $code;
        }

        // A configured Form wins over the table
        foreach ($forms as $code => $form) {
            $codesByClass[self::normalize($form['type'])] = $code;
        }

        $this->codesByClass = $codesByClass;
    }

    /**
     * Maps each form type class to the code of the configured Form with that type, else to its code in the table of
     * Sylius 1 form types. Anything else, such as a Form code or an unknown class, stays as it is. A class and its
     * code collapse to one code
     *
     * @param list<string> $forms
     *
     * @return list<string>
     */
    public function map(array $forms): array
    {
        return array_values(array_unique(array_map($this->mapOne(...), $forms)));
    }

    public function mapOne(string $form): string
    {
        return $this->codesByClass[self::normalize($form)] ?? $form;
    }

    /**
     * Whether the Form is an enabled Form in the app's config
     */
    public function isConfigured(string $form): bool
    {
        return isset($this->forms[$form]);
    }

    /**
     * PHP class names are case-insensitive, and a 2.x config key may have had a leading backslash
     */
    private static function normalize(string $class): string
    {
        return strtolower(ltrim($class, '\\'));
    }
}
