<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Lookup;

use Setono\SyliusTermsPlugin\Model\TermsVersionTranslationInterface;
use Sylius\Component\Channel\Model\ChannelInterface;

/**
 * Finds the Terms shown on a Form. It reads no request context, so it works outside Symfony forms, for example for an
 * order's confirmation email, with the order's channel, locale and checkout time
 */
interface FormTermsLookupInterface
{
    /**
     * Returns the Terms version in force at the given moment (default: now) for each enabled Terms placed on the Form
     * in the channel, read in the given locale, in position order.
     *
     * A Terms is skipped when it has no version in force at that moment, or when that version has no translation in
     * the locale. Placement (enabled, channels, Forms) is always the current one, whatever the moment. A Form that
     * isn't enabled in the config (setono_sylius_terms.forms) has no Terms, even if Terms still list its code.
     *
     * Each returned translation holds the name, label and content; its version holds the number and the Terms
     *
     * @param string $form a Form code
     *
     * @return list<TermsVersionTranslationInterface>
     */
    public function lookup(string $form, ChannelInterface $channel, string $localeCode, ?\DateTimeInterface $at = null): array;
}
