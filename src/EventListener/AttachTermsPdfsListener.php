<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\EventListener;

use Psr\Log\LoggerInterface;
use Setono\SyliusTermsPlugin\Lookup\FormTermsLookupInterface;
use Setono\SyliusTermsPlugin\Pdf\TermsPdfGeneratorInterface;
use Sylius\Bundle\CoreBundle\Mailer\Emails;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Mailer\Event\EmailSendEvent;
use Symfony\Component\Mime\Email;

/**
 * Attaches a Terms PDF to the order confirmation email, when it is first sent and when the admin resends it, for each
 * required Terms on checkout complete: the Terms version in force when checkout completed, in the order's locale (ADR
 * 0006). Registered only when setono_sylius_terms.order_confirmation_email.pdf is true
 */
final readonly class AttachTermsPdfsListener
{
    /** The Form whose required Terms the email attaches */
    private const FORM = 'checkout_complete';

    private const EMAIL_CODES = [Emails::ORDER_CONFIRMATION, Emails::ORDER_CONFIRMATION_RESENT];

    public function __construct(
        private FormTermsLookupInterface $formTermsLookup,
        private TermsPdfGeneratorInterface $termsPdfGenerator,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(EmailSendEvent $event): void
    {
        if (!in_array($event->getEmail()->getCode(), self::EMAIL_CODES, true)) {
            return;
        }

        // It is with Sylius' default symfony_mailer adapter
        $message = $event->getMessage();
        if (!$message instanceof Email) {
            return;
        }

        $order = $event->getData()['order'] ?? null;
        if (!$order instanceof OrderInterface) {
            return;
        }

        // From the order, never from the request, so a resend attaches what the first email attached
        $channel = $order->getChannel();
        $localeCode = $order->getLocaleCode();
        $checkoutCompletedAt = $order->getCheckoutCompletedAt();
        if (null === $channel || null === $localeCode || null === $checkoutCompletedAt) {
            return;
        }

        foreach ($this->formTermsLookup->lookup(self::FORM, $channel, $localeCode, $checkoutCompletedAt) as $translation) {
            $version = $translation->getVersion();
            $terms = $version->getTerms();

            // Answers aren't stored, so the email can't know whether an optional Terms was ticked (ADR 0001)
            if (null === $terms || !$terms->isRequired()) {
                continue;
            }

            try {
                $pdf = $this->termsPdfGenerator->generate($version, $localeCode, $channel, [
                    'order_number' => $order->getNumber(),
                ]);
            } catch (\Throwable $e) {
                // Checkout never breaks over a PDF
                $this->logger->error(sprintf(
                    'The Terms PDF of the Terms "%s" could not be generated for the order confirmation email of order #%s, so the email is sent without it: %s',
                    (string) $terms->getCode(),
                    (string) $order->getNumber(),
                    $e->getMessage(),
                ), [
                    'exception' => $e,
                    'order_number' => $order->getNumber(),
                    'terms_code' => $terms->getCode(),
                ]);

                continue;
            }

            $message->attach($pdf->content, $pdf->fileName, 'application/pdf');
        }
    }
}
