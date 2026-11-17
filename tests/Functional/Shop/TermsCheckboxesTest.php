<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Functional\Shop;

use PHPUnit\Framework\Attributes\Test;
use Setono\SyliusTermsPlugin\Tests\Functional\CreatesCartTrait;
use Setono\SyliusTermsPlugin\Tests\Functional\CreatesTermsTrait;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\DomCrawler\Form;

/**
 * The baseline Terms: terms_and_conditions is required on checkout complete, privacy_policy on registration and
 * contact. Both are in FASHION_WEB, translated into en_US and de_DE
 */
final class TermsCheckboxesTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use CreatesCartTrait;
    use CreatesTermsTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        restore_exception_handler();
    }

    #[Test]
    public function checkout_complete_with_a_required_terms_left_unticked_places_no_order_and_shows_the_error_on_its_checkbox(): void
    {
        $cart = $this->openCheckoutComplete();

        $crawler = $this->client->submit($this->client->getCrawler()->filter('form[name="sylius_checkout_complete"]')->form());

        self::assertResponseIsUnprocessable();
        self::assertSame(
            'You must accept the terms to continue.',
            self::getCheckboxError($crawler, 'sylius_checkout_complete_terms_terms_and_conditions'),
        );
        self::assertSame(OrderCheckoutStates::STATE_PAYMENT_SELECTED, $this->reloadOrder($cart)->getCheckoutState());
    }

    #[Test]
    public function checkout_complete_with_an_optional_terms_left_unticked_completes_the_order(): void
    {
        self::createTerms('newsletter_consent', ['checkout_complete'], [self::getChannel('FASHION_WEB')], required: false);

        $cart = $this->openCheckoutComplete();

        $form = $this->client->getCrawler()->filter('form[name="sylius_checkout_complete"]')->form();
        self::assertFalse(self::getCheckbox($form, 'sylius_checkout_complete[terms][newsletter_consent]')->hasValue(), 'The optional Terms is unticked');
        self::getCheckbox($form, 'sylius_checkout_complete[terms][terms_and_conditions]')->tick();

        $this->client->submit($form);

        self::assertResponseRedirects();
        self::assertSame(OrderCheckoutStates::STATE_COMPLETED, $this->reloadOrder($cart)->getCheckoutState());
    }

    /**
     * The contact form validates in the null validation groups, which the 2.x validation-group collector turned into
     * a constraint that never ran (#80)
     */
    #[Test]
    public function contact_enforces_required_terms(): void
    {
        $crawler = $this->client->request('GET', '/en_US/contact/');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[name="sylius_contact"]')->form([
            'sylius_contact[email]' => 'jane@example.com',
            'sylius_contact[message]' => 'Hello',
        ]);

        $crawler = $this->client->submit($form);

        self::assertResponseIsSuccessful();
        self::assertSame('You must accept the terms to continue.', self::getCheckboxError($crawler, 'sylius_contact_terms_privacy_policy'));

        $form = $crawler->filter('form[name="sylius_contact"]')->form();
        self::getCheckbox($form, 'sylius_contact[terms][privacy_policy]')->tick();

        $this->client->submit($form);

        self::assertResponseRedirects();
    }

    #[Test]
    public function the_checkbox_label_is_the_label_of_the_version_in_force(): void
    {
        self::mockTime('2026-11-16 12:00:00');
        $terms = self::createTerms('versioned_terms', ['contact'], [self::getChannel('FASHION_WEB')]);

        self::mockTime('2026-11-16 13:00:00');
        $terms->getTranslation('en_US')->setLabel('I accept the <b>second</b> [link:version]');
        self::getEntityManager()->flush();
        self::getEntityManager()->clear();

        // Version 1 was in force at 12:30, though the Terms has changed since
        self::mockTime('2026-11-16 12:30:00');
        self::assertSame('I accept the versioned_terms', $this->getContactLabel('sylius_contact_terms_versioned_terms'));
        self::assertSame(
            'I accept the <a href="/en_US/terms/versioned-terms" target="_blank">versioned_terms</a>',
            $this->getContactLabelHtml('sylius_contact_terms_versioned_terms'),
        );

        // The Label is escaped, and its Link placeholder becomes the link
        self::mockTime('2026-11-16 13:30:00');
        self::assertSame(
            'I accept the &lt;b&gt;second&lt;/b&gt; <a href="/en_US/terms/versioned-terms" target="_blank">version</a>',
            $this->getContactLabelHtml('sylius_contact_terms_versioned_terms'),
        );
    }

    #[Test]
    public function a_form_with_no_terms_renders_no_terms_markup(): void
    {
        $crawler = $this->client->request('GET', '/en_US/contact/');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('[data-test-terms]'), 'privacy_policy is shown in en_US');

        // The baseline Terms aren't translated into fr_FR, so the contact form has no Terms there
        $crawler = $this->client->request('GET', '/fr_FR/contact/');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('form[name="sylius_contact"]'));
        self::assertCount(0, $crawler->filter('[data-test-terms]'));
        self::assertCount(0, $crawler->filter('[name^="sylius_contact[terms]"]'));
    }

    /**
     * Logs in as the shop user, whose cart is ready to complete, and opens checkout complete
     */
    private function openCheckoutComplete(): OrderInterface
    {
        $user = self::getShopUser();
        $cart = self::createCartReadyToComplete($user);

        $this->client->loginUser($user, 'shop');
        $this->client->request('GET', '/en_US/checkout/complete');
        self::assertResponseIsSuccessful();

        return $cart;
    }

    private function reloadOrder(OrderInterface $order): OrderInterface
    {
        $id = $order->getId();
        self::getEntityManager()->clear();

        /** @var OrderRepositoryInterface<OrderInterface> $repository */
        $repository = self::getContainer()->get('sylius.repository.order');

        $reloaded = $repository->find($id);
        self::assertInstanceOf(OrderInterface::class, $reloaded);

        return $reloaded;
    }

    private function getContactLabel(string $checkboxId): string
    {
        $crawler = $this->client->request('GET', '/en_US/contact/');
        self::assertResponseIsSuccessful();

        return trim($crawler->filter(sprintf('label[for="%s"]', $checkboxId))->text());
    }

    private function getContactLabelHtml(string $checkboxId): string
    {
        $crawler = $this->client->request('GET', '/en_US/contact/');
        self::assertResponseIsSuccessful();

        return trim($crawler->filter(sprintf('label[for="%s"]', $checkboxId))->html());
    }

    private static function getCheckbox(Form $form, string $name): ChoiceFormField
    {
        $checkbox = $form[$name];
        self::assertInstanceOf(ChoiceFormField::class, $checkbox);

        return $checkbox;
    }

    /**
     * The error the shop theme renders in the row of the checkbox
     */
    private static function getCheckboxError(Crawler $crawler, string $checkboxId): string
    {
        $row = $crawler->filter(sprintf('#%s', $checkboxId))->ancestors()->filter('.error');
        self::assertCount(1, $row, 'The row of the checkbox is marked as having an error');

        return trim($row->filter('.invalid-feedback')->text());
    }
}
