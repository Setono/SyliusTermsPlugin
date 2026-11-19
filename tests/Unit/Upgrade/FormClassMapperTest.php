<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit\Upgrade;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Setono\SyliusTermsPlugin\Upgrade\FormClassMapper;

final class FormClassMapperTest extends TestCase
{
    /** The three default Forms, as the setono_sylius_terms.forms parameter holds them */
    private const DEFAULT_FORMS = [
        'checkout_complete' => ['type' => 'Sylius\Bundle\CoreBundle\Form\Type\Checkout\CompleteType'],
        'registration' => ['type' => 'Sylius\Bundle\ShopBundle\Form\Type\CustomerRegistrationType'],
        'contact' => ['type' => 'Sylius\Bundle\CoreBundle\Form\Type\ContactType'],
    ];

    #[Test]
    #[DataProvider('provideSylius1FormTypes')]
    public function it_maps_each_sylius_1_form_type_to_its_code(string $class, string $code): void
    {
        self::assertSame([$code], (new FormClassMapper(self::DEFAULT_FORMS))->map([$class]));
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function provideSylius1FormTypes(): \Generator
    {
        yield 'checkout complete' => ['Sylius\Bundle\CoreBundle\Form\Type\Checkout\CompleteType', 'checkout_complete'];
        yield 'registration' => ['Sylius\Bundle\CoreBundle\Form\Type\Customer\CustomerRegistrationType', 'registration'];
        yield 'contact' => ['Sylius\Bundle\CoreBundle\Form\Type\ContactType', 'contact'];
        yield 'checkout address' => ['Sylius\Bundle\CoreBundle\Form\Type\Checkout\AddressType', 'checkout_address'];
        yield 'checkout select shipping' => ['Sylius\Bundle\CoreBundle\Form\Type\Checkout\SelectShippingType', 'checkout_select_shipping'];
        yield 'checkout select payment' => ['Sylius\Bundle\CoreBundle\Form\Type\Checkout\SelectPaymentType', 'checkout_select_payment'];
        yield 'product review' => ['Sylius\Bundle\CoreBundle\Form\Type\Product\ProductReviewType', 'product_review'];
    }

    #[Test]
    public function the_table_has_the_seven_sylius_1_form_types(): void
    {
        self::assertCount(7, FormClassMapper::SYLIUS_1_FORM_CODES);
    }

    #[Test]
    public function a_configured_form_type_wins_over_the_table(): void
    {
        $mapper = new FormClassMapper(self::DEFAULT_FORMS + [
            'address' => ['type' => 'Sylius\Bundle\CoreBundle\Form\Type\Checkout\AddressType'],
            'newsletter' => ['type' => 'App\Form\Type\NewsletterType'],
        ]);

        self::assertSame(
            ['address', 'newsletter'],
            $mapper->map(['Sylius\Bundle\CoreBundle\Form\Type\Checkout\AddressType', 'App\Form\Type\NewsletterType']),
        );
    }

    #[Test]
    public function a_form_type_matches_whatever_its_case_and_with_a_leading_backslash(): void
    {
        $mapper = new FormClassMapper(self::DEFAULT_FORMS + ['newsletter' => ['type' => 'App\Form\Type\NewsletterType']]);

        self::assertSame(
            ['newsletter', 'checkout_complete'],
            $mapper->map(['\app\form\type\NEWSLETTERTYPE', '\Sylius\Bundle\CoreBundle\Form\Type\Checkout\CompleteType']),
        );
    }

    #[Test]
    public function an_unknown_class_stays_as_stored(): void
    {
        self::assertSame(
            ['App\Form\Type\NewsletterType'],
            (new FormClassMapper(self::DEFAULT_FORMS))->map(['App\Form\Type\NewsletterType']),
        );
    }

    #[Test]
    public function a_stored_code_is_left_alone(): void
    {
        self::assertSame(
            ['contact', 'newsletter'],
            (new FormClassMapper(self::DEFAULT_FORMS))->map(['contact', 'newsletter']),
        );
    }

    #[Test]
    public function a_class_and_its_code_on_the_same_terms_collapse_to_one_code(): void
    {
        self::assertSame(
            ['checkout_complete', 'contact'],
            (new FormClassMapper(self::DEFAULT_FORMS))->map([
                'Sylius\Bundle\CoreBundle\Form\Type\Checkout\CompleteType',
                'contact',
                'checkout_complete',
                'Sylius\Bundle\CoreBundle\Form\Type\ContactType',
            ]),
        );
    }

    #[Test]
    public function it_maps_one_form(): void
    {
        $mapper = new FormClassMapper(self::DEFAULT_FORMS);

        self::assertSame('checkout_complete', $mapper->mapOne('Sylius\Bundle\CoreBundle\Form\Type\Checkout\CompleteType'));
        self::assertSame('contact', $mapper->mapOne('contact'));
    }

    #[Test]
    public function only_the_enabled_forms_in_the_config_are_configured(): void
    {
        $mapper = new FormClassMapper(self::DEFAULT_FORMS);

        self::assertTrue($mapper->isConfigured('checkout_complete'));
        self::assertFalse($mapper->isConfigured('checkout_address'));
        self::assertFalse($mapper->isConfigured('Sylius\Bundle\CoreBundle\Form\Type\Checkout\CompleteType'));
    }
}
