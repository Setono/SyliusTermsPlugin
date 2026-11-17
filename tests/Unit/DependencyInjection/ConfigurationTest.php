<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit\DependencyInjection;

use Matthias\SymfonyConfigTest\PhpUnit\ConfigurationTestCaseTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Setono\SyliusTermsPlugin\DependencyInjection\Configuration;
use Sylius\Bundle\CoreBundle\Form\Type\Checkout\CompleteType;
use Sylius\Bundle\CoreBundle\Form\Type\ContactType;
use Sylius\Bundle\ShopBundle\Form\Type\CustomerRegistrationType;

final class ConfigurationTest extends TestCase
{
    use ConfigurationTestCaseTrait;

    private const CHECKOUT_COMPLETE = [
        'type' => CompleteType::class,
        'label' => 'setono_sylius_terms.form.terms.term_form.checkout_complete',
        'hooks' => ['sylius_shop.checkout.complete.content.form' => 50],
        'saved_event' => 'sylius.order.post_complete',
    ];

    private const REGISTRATION = [
        'type' => CustomerRegistrationType::class,
        'label' => 'setono_sylius_terms.form.terms.term_form.registration',
        'hooks' => ['sylius_shop.account.register.content.form' => 50],
        'saved_event' => 'sylius.customer.post_register',
    ];

    private const CONTACT = [
        'type' => ContactType::class,
        'label' => 'setono_sylius_terms.form.terms.term_form.contact',
        'hooks' => ['sylius_shop.contact.contact_request.content.form' => 50],
        'saved_event' => null,
    ];

    private const NEWSLETTER = [
        'type' => 'App\Form\Type\NewsletterType',
        'label' => 'app.form.newsletter',
        'hooks' => ['app.newsletter.form' => 50],
        'saved_event' => 'app.newsletter.post_subscribe',
    ];

    protected function getConfiguration(): Configuration
    {
        return new Configuration();
    }

    #[Test]
    public function it_defines_the_checkout_complete_registration_and_contact_forms_by_default(): void
    {
        $this->assertProcessedConfigurationEquals(
            [],
            ['forms' => [
                'checkout_complete' => self::CHECKOUT_COMPLETE,
                'registration' => self::REGISTRATION,
                'contact' => self::CONTACT,
            ]],
            'forms',
        );
    }

    #[Test]
    public function an_app_form_adds_to_the_three_defaults(): void
    {
        $this->assertProcessedConfigurationEquals(
            [['forms' => ['newsletter' => self::NEWSLETTER]]],
            ['forms' => [
                'checkout_complete' => self::CHECKOUT_COMPLETE,
                'registration' => self::REGISTRATION,
                'contact' => self::CONTACT,
                'newsletter' => self::NEWSLETTER,
            ]],
            'forms',
        );
    }

    #[Test]
    public function an_app_form_needs_only_a_type(): void
    {
        $this->assertProcessedConfigurationEquals(
            [['forms' => ['newsletter' => ['type' => 'App\Form\Type\NewsletterType']]]],
            ['forms' => [
                'checkout_complete' => self::CHECKOUT_COMPLETE,
                'registration' => self::REGISTRATION,
                'contact' => self::CONTACT,
                'newsletter' => [
                    'type' => 'App\Form\Type\NewsletterType',
                    'label' => 'setono_sylius_terms.form.terms.term_form.newsletter',
                    'hooks' => [],
                    'saved_event' => null,
                ],
            ]],
            'forms',
        );
    }

    #[Test]
    public function a_custom_form_may_set_no_hooks_to_render_the_terms_by_hand(): void
    {
        $this->assertProcessedConfigurationEquals(
            [['forms' => ['newsletter' => ['hooks' => []] + self::NEWSLETTER]]],
            ['forms' => [
                'checkout_complete' => self::CHECKOUT_COMPLETE,
                'registration' => self::REGISTRATION,
                'contact' => self::CONTACT,
                'newsletter' => ['hooks' => []] + self::NEWSLETTER,
            ]],
            'forms',
        );
    }

    #[Test]
    public function overriding_a_default_forms_hooks_keeps_its_other_keys(): void
    {
        $this->assertProcessedConfigurationEquals(
            [['forms' => ['checkout_complete' => ['hooks' => ['app.checkout.complete.terms' => 10]]]]],
            ['forms' => [
                'checkout_complete' => ['hooks' => ['app.checkout.complete.terms' => 10]] + self::CHECKOUT_COMPLETE,
                'registration' => self::REGISTRATION,
                'contact' => self::CONTACT,
            ]],
            'forms',
        );
    }

    #[Test]
    public function a_default_form_may_drop_its_hooks_and_saved_event(): void
    {
        $this->assertProcessedConfigurationEquals(
            [['forms' => ['checkout_complete' => ['hooks' => [], 'saved_event' => null]]]],
            ['forms' => [
                'checkout_complete' => ['hooks' => [], 'saved_event' => null] + self::CHECKOUT_COMPLETE,
                'registration' => self::REGISTRATION,
                'contact' => self::CONTACT,
            ]],
            'forms',
        );
    }

    #[Test]
    public function disabling_a_default_form_removes_it(): void
    {
        $this->assertProcessedConfigurationEquals(
            [['forms' => ['contact' => ['enabled' => false]]]],
            ['forms' => [
                'checkout_complete' => self::CHECKOUT_COMPLETE,
                'registration' => self::REGISTRATION,
            ]],
            'forms',
        );
    }

    #[Test]
    public function a_later_config_file_overrides_only_the_keys_it_sets(): void
    {
        $this->assertProcessedConfigurationEquals(
            [
                ['forms' => ['checkout_complete' => ['hooks' => ['app.checkout.complete.terms' => 10]]]],
                ['forms' => ['checkout_complete' => ['label' => 'app.form.checkout']]],
                ['forms' => ['newsletter' => self::NEWSLETTER]],
                ['forms' => ['newsletter' => ['hooks' => ['app.footer.newsletter' => 20]]]],
            ],
            ['forms' => [
                'checkout_complete' => ['hooks' => ['app.checkout.complete.terms' => 10], 'label' => 'app.form.checkout'] + self::CHECKOUT_COMPLETE,
                'registration' => self::REGISTRATION,
                'contact' => self::CONTACT,
                'newsletter' => ['hooks' => ['app.footer.newsletter' => 20]] + self::NEWSLETTER,
            ]],
            'forms',
        );
    }

    #[Test]
    public function a_form_code_keeps_its_dashes(): void
    {
        $this->assertProcessedConfigurationEquals(
            [['forms' => ['footer-newsletter' => self::NEWSLETTER]]],
            ['forms' => [
                'checkout_complete' => self::CHECKOUT_COMPLETE,
                'registration' => self::REGISTRATION,
                'contact' => self::CONTACT,
                'footer-newsletter' => self::NEWSLETTER,
            ]],
            'forms',
        );
    }

    #[Test]
    public function an_app_form_without_a_type_is_invalid(): void
    {
        $this->assertConfigurationIsInvalid(
            [['forms' => ['newsletter' => ['hooks' => ['app.newsletter.form' => 50]]]]],
            'The Form "newsletter" has no type',
        );
    }

    #[Test]
    public function hooks_that_are_not_a_map_of_priorities_are_invalid(): void
    {
        $this->assertConfigurationIsInvalid(
            [['forms' => ['newsletter' => ['hooks' => ['app.newsletter.form']] + self::NEWSLETTER]]],
            'The hooks must map each hook name to an integer priority',
        );
        $this->assertConfigurationIsInvalid(
            [['forms' => ['newsletter' => ['hooks' => ['app.newsletter.form' => 'high']] + self::NEWSLETTER]]],
            'The hooks must map each hook name to an integer priority',
        );
        $this->assertConfigurationIsInvalid(
            [['forms' => ['newsletter' => ['hooks' => 'app.newsletter.form'] + self::NEWSLETTER]]],
            'The hooks must map each hook name to an integer priority',
        );
    }

    #[Test]
    public function it_serves_terms_under_the_terms_path_by_default(): void
    {
        $this->assertProcessedConfigurationEquals(
            [],
            ['routing' => ['terms' => 'terms']],
            'routing',
        );
    }

    #[Test]
    public function an_empty_terms_path_is_invalid(): void
    {
        $this->assertConfigurationIsInvalid(
            [['routing' => ['terms' => '']]],
            'The path "setono_sylius_terms.routing.terms" cannot contain an empty value',
        );
        $this->assertConfigurationIsInvalid(
            [['routing' => ['terms' => null]]],
            'The path "setono_sylius_terms.routing.terms" cannot contain an empty value',
        );
    }

    #[Test]
    public function the_terms_pdfs_are_off_by_default(): void
    {
        $this->assertProcessedConfigurationEquals(
            [],
            ['order_confirmation_email' => ['pdf' => false]],
            'order_confirmation_email',
        );
    }

    #[Test]
    public function an_app_switches_the_terms_pdfs_on(): void
    {
        $this->assertProcessedConfigurationEquals(
            [['order_confirmation_email' => ['pdf' => true]]],
            ['order_confirmation_email' => ['pdf' => true]],
            'order_confirmation_email',
        );
    }
}
