<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit\EventListener;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Log\LoggerInterface;
use Setono\SyliusTermsPlugin\EventListener\AttachTermsPdfsListener;
use Setono\SyliusTermsPlugin\Lookup\FormTermsLookupInterface;
use Setono\SyliusTermsPlugin\Model\Terms;
use Setono\SyliusTermsPlugin\Model\TermsVersion;
use Setono\SyliusTermsPlugin\Model\TermsVersionTranslation;
use Setono\SyliusTermsPlugin\Model\TermsVersionTranslationInterface;
use Setono\SyliusTermsPlugin\Pdf\TermsPdf;
use Setono\SyliusTermsPlugin\Pdf\TermsPdfGeneratorInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Mailer\Event\EmailSendEvent;
use Sylius\Component\Mailer\Model\Email as EmailModel;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\RawMessage;

final class AttachTermsPdfsListenerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<FormTermsLookupInterface> */
    private ObjectProphecy $formTermsLookup;

    /** @var ObjectProphecy<TermsPdfGeneratorInterface> */
    private ObjectProphecy $termsPdfGenerator;

    /** @var ObjectProphecy<LoggerInterface> */
    private ObjectProphecy $logger;

    private Channel $channel;

    private Order $order;

    private AttachTermsPdfsListener $listener;

    protected function setUp(): void
    {
        $this->formTermsLookup = $this->prophesize(FormTermsLookupInterface::class);
        $this->termsPdfGenerator = $this->prophesize(TermsPdfGeneratorInterface::class);
        $this->logger = $this->prophesize(LoggerInterface::class);

        $this->channel = new Channel();
        $this->channel->setCode('WEB');

        $this->order = new Order();
        $this->order->setNumber('000042');
        $this->order->setChannel($this->channel);
        $this->order->setLocaleCode('da_DK');
        $this->order->setCheckoutCompletedAt(new \DateTimeImmutable('2026-11-16 12:00:00'));

        $this->listener = new AttachTermsPdfsListener(
            $this->formTermsLookup->reveal(),
            $this->termsPdfGenerator->reveal(),
            $this->logger->reveal(),
        );
    }

    /**
     * The lookup returns the Terms on checkout complete in position order, with the version in force when checkout
     * completed, in the order's locale. A Terms with no such version isn't among them
     */
    #[Test]
    #[DataProvider('provideOrderConfirmationEmailCodes')]
    public function it_attaches_the_pdf_of_each_required_terms_on_checkout_complete_in_position_order(string $emailCode): void
    {
        $termsAndConditions = self::inForce('terms_and_conditions', required: true);
        $newsletter = self::inForce('newsletter', required: false);
        $returnPolicy = self::inForce('return_policy', required: true);

        $this->formTermsLookup
            ->lookup('checkout_complete', $this->channel, 'da_DK', $this->order->getCheckoutCompletedAt())
            ->willReturn([$termsAndConditions, $newsletter, $returnPolicy])
            ->shouldBeCalledOnce()
        ;
        $this->expectPdf($termsAndConditions, new TermsPdf('%PDF-terms', 'handelsbetingelser-v3.pdf'));
        $this->expectPdf($returnPolicy, new TermsPdf('%PDF-returns', 'returpolitik-v1.pdf'));
        $this->termsPdfGenerator->generate($newsletter->getVersion(), Argument::cetera())->shouldNotBeCalled();

        $message = new Email();
        ($this->listener)($this->createEvent($emailCode, $message));

        self::assertSame([
            ['handelsbetingelser-v3.pdf', 'application/pdf', '%PDF-terms'],
            ['returpolitik-v1.pdf', 'application/pdf', '%PDF-returns'],
        ], self::getAttachments($message));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideOrderConfirmationEmailCodes(): iterable
    {
        yield 'first send' => ['order_confirmation'];
        yield 'admin resend' => ['order_confirmation_resent'];
    }

    #[Test]
    public function it_ignores_other_emails(): void
    {
        $this->formTermsLookup->lookup(Argument::cetera())->shouldNotBeCalled();

        $message = new Email();
        ($this->listener)($this->createEvent('shipment_confirmation', $message));

        self::assertSame([], $message->getAttachments());
    }

    #[Test]
    public function it_ignores_messages_that_are_not_a_symfony_email(): void
    {
        $this->formTermsLookup->lookup(Argument::cetera())->shouldNotBeCalled();

        ($this->listener)($this->createEvent('order_confirmation', new RawMessage('A message from another mailer adapter')));
    }

    /**
     * @param array<string, mixed> $data
     */
    #[Test]
    #[DataProvider('provideDataWithoutACompletedOrder')]
    public function it_ignores_emails_without_a_completed_order(array $data): void
    {
        $this->formTermsLookup->lookup(Argument::cetera())->shouldNotBeCalled();

        $message = new Email();
        ($this->listener)(new EmailSendEvent($message, self::createEmail('order_confirmation'), $data));

        self::assertSame([], $message->getAttachments());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideDataWithoutACompletedOrder(): iterable
    {
        yield 'no order' => [[]];
        yield 'not an order' => [['order' => '000042']];

        yield 'checkout not completed' => [['order' => self::createOrder(new Channel(), 'da_DK', null)]];
        yield 'no locale' => [['order' => self::createOrder(new Channel(), null, new \DateTimeImmutable())]];
        yield 'no channel' => [['order' => self::createOrder(null, 'da_DK', new \DateTimeImmutable())]];
    }

    private static function createOrder(?Channel $channel, ?string $localeCode, ?\DateTimeImmutable $checkoutCompletedAt): Order
    {
        $order = new Order();
        $order->setCheckoutCompletedAt($checkoutCompletedAt);

        // Both setters reject null, which a new order has
        if (null !== $channel) {
            $order->setChannel($channel);
        }

        if (null !== $localeCode) {
            $order->setLocaleCode($localeCode);
        }

        return $order;
    }

    #[Test]
    public function it_skips_a_version_that_belongs_to_no_terms(): void
    {
        $translation = new TermsVersionTranslation();
        $translation->setLocale('da_DK');
        (new TermsVersion())->addTranslation($translation);

        $this->formTermsLookup->lookup(Argument::cetera())->willReturn([$translation]);
        $this->termsPdfGenerator->generate(Argument::cetera())->shouldNotBeCalled();

        $message = new Email();
        ($this->listener)($this->createEvent('order_confirmation', $message));

        self::assertSame([], $message->getAttachments());
    }

    #[Test]
    public function it_logs_an_error_and_attaches_the_other_pdfs_when_one_cannot_be_generated(): void
    {
        $termsAndConditions = self::inForce('terms_and_conditions', required: true);
        $privacyPolicy = self::inForce('privacy_policy', required: true);
        $returnPolicy = self::inForce('return_policy', required: true);

        $this->formTermsLookup->lookup(Argument::cetera())->willReturn([$termsAndConditions, $privacyPolicy, $returnPolicy]);
        $this->expectPdf($termsAndConditions, new TermsPdf('%PDF-terms', 'handelsbetingelser-v3.pdf'));
        $this->expectPdf($returnPolicy, new TermsPdf('%PDF-returns', 'returpolitik-v1.pdf'));

        $exception = new \RuntimeException('The PDF engine failed');
        $this->termsPdfGenerator->generate($privacyPolicy->getVersion(), Argument::cetera())->willThrow($exception);

        $this->logger->error(
            'The Terms PDF of the Terms "privacy_policy" could not be generated for the order confirmation email of order #000042, so the email is sent without it: The PDF engine failed',
            [
                'exception' => $exception,
                'order_number' => '000042',
                'terms_code' => 'privacy_policy',
            ],
        )->shouldBeCalledOnce();

        $message = new Email();
        ($this->listener)($this->createEvent('order_confirmation', $message));

        self::assertSame([
            ['handelsbetingelser-v3.pdf', 'application/pdf', '%PDF-terms'],
            ['returpolitik-v1.pdf', 'application/pdf', '%PDF-returns'],
        ], self::getAttachments($message));
    }

    /**
     * The order number goes into the context of the errors the generator logs for images that can't be downloaded
     */
    private function expectPdf(TermsVersionTranslationInterface $translation, TermsPdf $pdf): void
    {
        $this->termsPdfGenerator
            ->generate($translation->getVersion(), 'da_DK', $this->channel, ['order_number' => '000042'])
            ->willReturn($pdf)
            ->shouldBeCalledOnce()
        ;
    }

    private function createEvent(string $emailCode, mixed $message): EmailSendEvent
    {
        return new EmailSendEvent($message, self::createEmail($emailCode), [
            'order' => $this->order,
            'channel' => $this->channel,
            'localeCode' => 'da_DK',
        ]);
    }

    private static function createEmail(string $code): EmailModel
    {
        $email = new EmailModel();
        $email->setCode($code);

        return $email;
    }

    private static function inForce(string $code, bool $required): TermsVersionTranslationInterface
    {
        $terms = new Terms();
        $terms->setCode($code);
        $terms->setRequired($required);

        $version = new TermsVersion();
        $terms->addVersion($version);

        $translation = new TermsVersionTranslation();
        $translation->setLocale('da_DK');
        $version->addTranslation($translation);

        return $translation;
    }

    /**
     * @return list<array{string, string, string}> the file name, media type and body of each attachment, in order
     */
    private static function getAttachments(Email $message): array
    {
        return array_values(array_map(static fn (DataPart $attachment): array => [
            (string) $attachment->getFilename(),
            sprintf('%s/%s', $attachment->getMediaType(), $attachment->getMediaSubtype()),
            $attachment->getBody(),
        ], $message->getAttachments()));
    }
}
