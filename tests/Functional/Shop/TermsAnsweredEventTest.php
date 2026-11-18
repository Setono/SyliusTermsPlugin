<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Functional\Shop;

use PHPUnit\Framework\Attributes\Test;
use Setono\SyliusTermsPlugin\Event\Answer;
use Setono\SyliusTermsPlugin\Event\TermsAnsweredEvent;
use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Setono\SyliusTermsPlugin\Model\TermsVersionTranslationInterface;
use Setono\SyliusTermsPlugin\Repository\TermsRepositoryInterface;
use Setono\SyliusTermsPlugin\Tests\Application\EventListener\TermsAnsweredEventRecorder;
use Setono\SyliusTermsPlugin\Tests\Functional\CreatesCartTrait;
use Setono\SyliusTermsPlugin\Tests\Functional\CreatesTermsTrait;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\DomCrawler\Form;

/**
 * The baseline Terms: terms_and_conditions is required on checkout complete, privacy_policy on registration and
 * contact. The test app records each TermsAnsweredEvent with what was saved of its data when it was dispatched
 */
final class TermsAnsweredEventTest extends WebTestCase
{
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
    public function checkout_complete_dispatches_once_after_the_order_is_completed(): void
    {
        $cart = $this->openCheckoutComplete();

        $form = $this->getCheckoutCompleteForm();
        self::getCheckbox($form, 'sylius_checkout_complete[terms][terms_and_conditions]')->tick();
        $this->client->submit($form);

        self::assertResponseRedirects();

        $recorded = self::getRecorded();
        self::assertCount(1, $recorded);
        self::assertSame('checkout_complete', $recorded[0]['event']->form);

        $order = $recorded[0]['event']->data;
        self::assertInstanceOf(OrderInterface::class, $order);
        self::assertSame($cart->getId(), $order->getId());
        self::assertSame(OrderCheckoutStates::STATE_COMPLETED, $recorded[0]['storedCheckoutState'], 'The completed order was flushed before the event');
    }

    /**
     * Sylius' OrderIntegrityChecker stops the completion on sylius.order.pre_complete, and flushes as it does, when the
     * order's total has changed since the customer saw it
     */
    #[Test]
    public function a_checkout_that_the_integrity_checker_stops_dispatches_nothing(): void
    {
        $cart = $this->openCheckoutComplete();

        $item = $cart->getItems()->first();
        self::assertInstanceOf(OrderItemInterface::class, $item);
        $channel = $cart->getChannel();
        self::assertInstanceOf(ChannelInterface::class, $channel);
        $channelPricing = $item->getVariant()?->getChannelPricingForChannel($channel);
        self::assertNotNull($channelPricing);
        $channelPricing->setPrice((int) $channelPricing->getPrice() + 1000);
        self::getEntityManager()->flush();

        $form = $this->getCheckoutCompleteForm();
        self::getCheckbox($form, 'sylius_checkout_complete[terms][terms_and_conditions]')->tick();
        $this->client->submit($form);

        self::assertResponseRedirects('/en_US/checkout/complete');
        self::assertSame(OrderCheckoutStates::STATE_PAYMENT_SELECTED, $this->reloadOrder($cart)->getCheckoutState());
        self::assertSame([], self::getRecorded());
    }

    #[Test]
    public function registration_dispatches_after_the_customer_is_saved(): void
    {
        $crawler = $this->client->request('GET', '/en_US/register');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[name="sylius_shop_customer_registration"]')->form([
            'sylius_shop_customer_registration[firstName]' => 'Jane',
            'sylius_shop_customer_registration[lastName]' => 'Doe',
            'sylius_shop_customer_registration[email]' => 'jane.doe@example.com',
            'sylius_shop_customer_registration[user][plainPassword][first]' => 'secret-password',
            'sylius_shop_customer_registration[user][plainPassword][second]' => 'secret-password',
        ]);
        self::getCheckbox($form, 'sylius_shop_customer_registration[terms][privacy_policy]')->tick();
        $this->client->submit($form);

        self::assertResponseRedirects();

        $recorded = self::getRecorded();
        self::assertCount(1, $recorded);
        self::assertSame('registration', $recorded[0]['event']->form);

        $customer = $recorded[0]['event']->data;
        self::assertInstanceOf(CustomerInterface::class, $customer);
        self::assertSame('jane.doe@example.com', $customer->getEmail());
        self::assertNotNull($recorded[0]['id'], 'The customer had an ID when the event was dispatched');
    }

    #[Test]
    public function contact_dispatches_after_a_valid_submit(): void
    {
        $form = $this->getContactForm();
        self::getCheckbox($form, 'sylius_contact[terms][privacy_policy]')->tick();
        $this->client->submit($form);

        self::assertResponseRedirects();

        $recorded = self::getRecorded();
        self::assertCount(1, $recorded);
        self::assertSame('contact', $recorded[0]['event']->form);
        self::assertSame(['email' => 'jane@example.com', 'message' => 'Hello'], $recorded[0]['event']->data);
    }

    #[Test]
    public function an_invalid_submit_dispatches_nothing(): void
    {
        // The required privacy_policy is left unticked
        $this->client->submit($this->getContactForm());

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-test-terms] .invalid-feedback');
        self::assertSame([], self::getRecorded());

        $this->openCheckoutComplete();
        $this->client->submit($this->getCheckoutCompleteForm());

        self::assertResponseIsUnprocessable();
        self::assertSame([], self::getRecorded());
    }

    #[Test]
    public function the_payload_carries_every_terms_version_shown_with_its_answer(): void
    {
        self::createTerms('newsletter_consent', ['checkout_complete'], [self::getChannel('FASHION_WEB')], required: false);

        // Changing the label of terms_and_conditions creates its version 2
        $termsAndConditions = self::getTermsByCode('terms_and_conditions');
        $termsAndConditions->getTranslation('en_US')->setLabel('I accept the new [link:terms and conditions]');
        self::getEntityManager()->flush();

        $cart = $this->openCheckoutComplete();

        $form = $this->getCheckoutCompleteForm();
        self::getCheckbox($form, 'sylius_checkout_complete[terms][terms_and_conditions]')->tick();
        $this->client->submit($form);

        self::assertResponseRedirects();

        $recorded = self::getRecorded();
        self::assertCount(1, $recorded);

        $event = $recorded[0]['event'];
        self::assertSame('checkout_complete', $event->form);
        self::assertInstanceOf(OrderInterface::class, $event->data);
        self::assertSame($cart->getId(), $event->data->getId());
        self::assertSame('FASHION_WEB', $event->channel->getCode());
        self::assertSame('en_US', $event->localeCode);

        self::assertSame([
            ['terms_and_conditions', 2, 'I accept the new [link:terms and conditions]', true],
            ['newsletter_consent', 1, 'I accept the [link:newsletter_consent]', false],
        ], array_map(static function (Answer $answer) use ($event): array {
            $translation = $answer->version->getTranslation($event->localeCode);
            self::assertInstanceOf(TermsVersionTranslationInterface::class, $translation);

            return [$answer->version->getTerms()?->getCode(), $answer->version->getNumber(), $translation->getLabel(), $answer->ticked];
        }, $event->answers));
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

    private function getCheckoutCompleteForm(): Form
    {
        return $this->client->getCrawler()->filter('form[name="sylius_checkout_complete"]')->form();
    }

    private function getContactForm(): Form
    {
        $crawler = $this->client->request('GET', '/en_US/contact/');
        self::assertResponseIsSuccessful();

        return $crawler->filter('form[name="sylius_contact"]')->form([
            'sylius_contact[email]' => 'jane@example.com',
            'sylius_contact[message]' => 'Hello',
        ]);
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

    private static function getTermsByCode(string $code): TermsInterface
    {
        /** @var TermsRepositoryInterface $repository */
        $repository = self::getContainer()->get('setono_sylius_terms.repository.terms');

        $terms = $repository->findOneBy(['code' => $code]);
        self::assertInstanceOf(TermsInterface::class, $terms);

        return $terms;
    }

    private static function getCheckbox(Form $form, string $name): ChoiceFormField
    {
        $checkbox = $form[$name];
        self::assertInstanceOf(ChoiceFormField::class, $checkbox);

        return $checkbox;
    }

    /**
     * The events the last request dispatched. The test client boots a new kernel for each request after the first
     *
     * @return list<array{event: TermsAnsweredEvent, id: mixed, storedCheckoutState: string|null}>
     *
     * @phpstan-impure each request may record more
     */
    private static function getRecorded(): array
    {
        $recorder = self::getContainer()->get(TermsAnsweredEventRecorder::class);
        self::assertInstanceOf(TermsAnsweredEventRecorder::class, $recorder);

        return $recorder->getRecorded();
    }
}
