<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Functional\Pdf;

use Monolog\Handler\TestHandler;
use PHPUnit\Framework\Attributes\Test;
use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Setono\SyliusTermsPlugin\Repository\TermsRepositoryInterface;
use Setono\SyliusTermsPlugin\Tests\Application\HttpClient\MockResponseFactory;
use Setono\SyliusTermsPlugin\Tests\Application\Pdf\RecordingHtmlToPdfRenderer;
use Setono\SyliusTermsPlugin\Tests\Functional\CreatesCartTrait;
use Setono\SyliusTermsPlugin\Tests\Functional\CreatesTermsTrait;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Bundle\CoreBundle\Mailer\OrderEmailManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

/**
 * The test environment switches the Terms PDFs on. Its PDF renderer records the HTML it is given and renders it with
 * the real dompdf, and its HTTP client answers from the MockResponseFactory, with a 404 for every URL a test doesn't
 * add. The baseline terms_and_conditions is required on checkout complete in FASHION_WEB. Its Content has a relative
 * image, which the 404 replaces by its alt text
 */
final class OrderConfirmationEmailTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use CreatesCartTrait;
    use CreatesTermsTrait;

    /** A 1x1 PNG */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    protected function tearDown(): void
    {
        parent::tearDown();

        restore_exception_handler();
    }

    #[Test]
    public function completing_checkout_attaches_a_terms_pdf_per_required_terms_on_checkout_complete_in_position_order(): void
    {
        $client = self::createClient();
        $channel = self::getChannel('FASHION_WEB');

        // Before terms_and_conditions
        self::createTerms('delivery_terms', ['checkout_complete'], [$channel], position: 0);
        self::createTerms('newsletter_consent', ['checkout_complete'], [$channel], required: false);
        self::createTerms('contact_terms', ['contact'], [$channel]);
        self::createTerms('other_channel_terms', ['checkout_complete'], [self::createChannel('OTHER_WEB')]);
        self::createTerms('german_terms', ['checkout_complete'], [$channel], localeCodes: ['de_DE']);
        self::createTerms('disabled_terms', ['checkout_complete'], [$channel], enabled: false);

        $order = $this->completeCheckout($client);

        $attachments = self::getAttachments(self::getSentEmail());
        self::assertSame(['delivery-terms-en-us-v1.pdf', 'terms-and-conditions-v1.pdf'], array_keys($attachments));

        foreach ($attachments as $fileName => $attachment) {
            self::assertSame('application/pdf', $attachment['type'], $fileName);
            self::assertStringStartsWith('%PDF-', $attachment['body'], $fileName);
        }

        // The baseline Content's image is the only error
        self::assertSame(
            [['terms_and_conditions', 'http://localhost/images/terms-wide.png']],
            array_map(static fn (array $context): array => [$context['terms_code'] ?? null, $context['image_url'] ?? null], self::getErrorContexts()),
        );
        self::assertSame($order->getNumber(), self::getErrorContexts()[0]['order_number'] ?? null);
    }

    /**
     * Placement and the required flag are current, but the version is the one in force when checkout completed
     */
    #[Test]
    public function resending_the_confirmation_from_the_admin_attaches_the_versions_in_force_at_checkout(): void
    {
        $client = self::createClient();
        $order = $this->completeCheckout($client);

        $checkoutCompletedAt = $order->getCheckoutCompletedAt();
        self::assertNotNull($checkoutCompletedAt);
        self::mockTime(\DateTimeImmutable::createFromInterface($checkoutCompletedAt)->modify('+1 hour'));

        // Version 2 of terms_and_conditions, and a Terms whose first version comes after the order
        $termsAndConditions = self::getTerms('terms_and_conditions');
        $termsAndConditions->getTranslation('en_US')->setName('Terms of Sale');
        $termsAndConditions->getTranslation('en_US')->setContent('The second version');
        self::getEntityManager()->flush();
        self::createTerms('later_terms', ['checkout_complete'], [self::getChannel('FASHION_WEB')]);

        $client->loginUser(self::getAdmin(), 'admin');
        $crawler = $client->request('GET', sprintf('/admin/orders/%d', (int) $order->getId()));
        self::assertResponseIsSuccessful();

        $client->click($crawler->filter('a[href*="/resend-confirmation-email"]')->first()->link());
        self::assertResponseRedirects();

        self::assertSame(['terms-and-conditions-v1.pdf'], array_keys(self::getAttachments(self::getSentEmail())));

        $renders = self::getRecordingRenderer()->getRenders();
        self::assertCount(1, $renders);
        self::assertStringContainsString('<h1>Terms and Conditions</h1>', $renders[0]['html']);
        self::assertStringContainsString('These terms apply to every order placed in this shop.', $renders[0]['html']);
        self::assertStringNotContainsString('The second version', $renders[0]['html']);
    }

    /**
     * The test_pdf_off environment is the test environment with the switch off, as it is by default
     */
    #[Test]
    public function with_the_switch_off_the_confirmation_has_no_attachments(): void
    {
        $client = self::createClient(['environment' => 'test_pdf_off']);

        $this->completeCheckout($client);

        self::assertSame([], self::getSentEmail()->getAttachments());
    }

    #[Test]
    public function a_pdf_that_cannot_be_rendered_is_left_out_and_logged_and_the_order_completes(): void
    {
        $client = self::createClient();

        $terms = self::createTerms('broken_terms', ['checkout_complete'], [self::getChannel('FASHION_WEB')]);
        $terms->getTranslation('en_US')->setContent(RecordingHtmlToPdfRenderer::FAILING_TEXT);
        self::getEntityManager()->flush();

        $order = $this->completeCheckout($client);

        self::assertSame(OrderCheckoutStates::STATE_COMPLETED, $order->getCheckoutState());
        self::assertSame(['terms-and-conditions-v1.pdf'], array_keys(self::getAttachments(self::getSentEmail())));

        $errors = array_values(array_filter(
            self::getErrorContexts(),
            static fn (array $context): bool => 'broken_terms' === ($context['terms_code'] ?? null),
        ));
        self::assertCount(1, $errors);
        self::assertSame($order->getNumber(), $errors[0]['order_number'] ?? null);
        self::assertInstanceOf(\RuntimeException::class, $errors[0]['exception'] ?? null);
    }

    /**
     * Two orders sent in the same kernel, so they share the PDF cache, as orders do in production
     */
    #[Test]
    public function an_image_that_cannot_be_downloaded_shows_its_alt_text_is_logged_and_is_tried_again_for_the_next_order(): void
    {
        self::bootKernel();

        // The baseline image downloads, so the terms_and_conditions PDF is complete and cached
        self::getMockResponseFactory()->add('http://localhost/images/terms-wide.png', new MockResponse(
            (string) base64_decode(self::PNG, true),
            ['response_headers' => ['content-type' => 'image/png']],
        ));

        // Changing the Content saves version 2
        $terms = self::createTerms('map_terms', ['checkout_complete'], [self::getChannel('FASHION_WEB')]);
        $terms->getTranslation('en_US')->setContent('![Our warehouse](https://images.example.com/warehouse.png)');
        self::getEntityManager()->flush();

        $firstOrder = self::completeOrder();
        $secondOrder = self::completeOrder();

        $emails = self::getMailerMessages();
        self::assertCount(2, $emails);

        foreach ($emails as $email) {
            self::assertInstanceOf(Email::class, $email);
            self::assertSame(['terms-and-conditions-v1.pdf', 'map-terms-en-us-v2.pdf'], array_keys(self::getAttachments($email)));
        }

        // terms_and_conditions comes from the cache for the second order, but map_terms is rendered again
        $renders = self::getRecordingRenderer()->getRenders();
        self::assertCount(3, $renders);
        self::assertStringContainsString('<h1>Terms and Conditions</h1>', $renders[0]['html']);

        foreach ([$renders[1], $renders[2]] as $render) {
            self::assertStringContainsString('<h1>map_terms (en_US)</h1>', $render['html']);
            self::assertStringContainsString('Our warehouse', $render['html']);
            self::assertStringNotContainsString('<img', $render['html']);
        }

        self::assertSame([
            [$firstOrder->getNumber(), 'map_terms', 'https://images.example.com/warehouse.png'],
            [$secondOrder->getNumber(), 'map_terms', 'https://images.example.com/warehouse.png'],
        ], array_map(static fn (array $context): array => [
            $context['order_number'] ?? null,
            $context['terms_code'] ?? null,
            $context['image_url'] ?? null,
        ], self::getErrorContexts()));
    }

    /**
     * Logs in as the shop user, whose cart is ready to complete, ticks every Terms checkbox on checkout complete and
     * completes the order
     */
    private function completeCheckout(KernelBrowser $client): OrderInterface
    {
        $user = self::getShopUser();
        $cart = self::createCartReadyToComplete($user);

        $client->loginUser($user, 'shop');
        $crawler = $client->request('GET', '/en_US/checkout/complete');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[name="sylius_checkout_complete"]')->form();
        foreach ($form->all() as $name => $field) {
            if ($field instanceof ChoiceFormField && str_starts_with($name, 'sylius_checkout_complete[terms]')) {
                $field->tick();
            }
        }

        $client->submit($form);
        self::assertResponseRedirects();

        $order = self::reloadOrder($cart);
        self::assertSame(OrderCheckoutStates::STATE_COMPLETED, $order->getCheckoutState());

        return $order;
    }

    /**
     * Completes an order in code and sends its confirmation the way the shop does after checkout complete
     */
    private static function completeOrder(): OrderInterface
    {
        $order = self::createCartReadyToComplete(self::getShopUser());

        /** @var StateMachineInterface $stateMachine */
        $stateMachine = self::getContainer()->get('sylius_abstraction.state_machine');
        $stateMachine->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_COMPLETE);
        self::getEntityManager()->flush();

        /** @var OrderEmailManagerInterface $orderEmailManager */
        $orderEmailManager = self::getContainer()->get(OrderEmailManagerInterface::class);
        $orderEmailManager->sendConfirmationEmail($order);

        return $order;
    }

    private static function reloadOrder(OrderInterface $order): OrderInterface
    {
        $id = $order->getId();
        self::getEntityManager()->clear();

        /** @var OrderRepositoryInterface<OrderInterface> $repository */
        $repository = self::getContainer()->get('sylius.repository.order');

        $reloaded = $repository->find($id);
        self::assertInstanceOf(OrderInterface::class, $reloaded);

        return $reloaded;
    }

    private static function getTerms(string $code): TermsInterface
    {
        /** @var TermsRepositoryInterface $repository */
        $repository = self::getContainer()->get('setono_sylius_terms.repository.terms');

        $terms = $repository->findOneBy(['code' => $code]);
        self::assertInstanceOf(TermsInterface::class, $terms);

        return $terms;
    }

    private static function getAdmin(): AdminUserInterface
    {
        /** @var UserRepositoryInterface<AdminUserInterface> $repository */
        $repository = self::getContainer()->get('sylius.repository.admin_user');

        $admin = $repository->findOneBy(['username' => 'sylius']);
        self::assertInstanceOf(AdminUserInterface::class, $admin);

        return $admin;
    }

    /**
     * The one email the last request sent
     */
    private static function getSentEmail(): Email
    {
        self::assertEmailCount(1);

        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);

        return $email;
    }

    /**
     * @return array<string, array{type: string, body: string}> the media type and body of each attachment, by file name, in order
     */
    private static function getAttachments(Email $email): array
    {
        $attachments = [];

        foreach ($email->getAttachments() as $attachment) {
            self::assertInstanceOf(DataPart::class, $attachment);

            $attachments[(string) $attachment->getFilename()] = [
                'type' => sprintf('%s/%s', $attachment->getMediaType(), $attachment->getMediaSubtype()),
                'body' => $attachment->getBody(),
            ];
        }

        return $attachments;
    }

    /**
     * The context of each error logged since the kernel booted
     *
     * @return list<array<string, mixed>>
     */
    private static function getErrorContexts(): array
    {
        $handler = self::getContainer()->get('monolog.handler.test');
        self::assertInstanceOf(TestHandler::class, $handler);

        $contexts = [];

        foreach ($handler->getRecords() as $record) {
            /** @var array<string, mixed> $context */
            $context = $record['context'];
            $contexts[] = $context;
        }

        return $contexts;
    }

    private static function getRecordingRenderer(): RecordingHtmlToPdfRenderer
    {
        $renderer = self::getContainer()->get(RecordingHtmlToPdfRenderer::class);
        self::assertInstanceOf(RecordingHtmlToPdfRenderer::class, $renderer);

        return $renderer;
    }

    private static function getMockResponseFactory(): MockResponseFactory
    {
        $factory = self::getContainer()->get(MockResponseFactory::class);
        self::assertInstanceOf(MockResponseFactory::class, $factory);

        return $factory;
    }
}
