<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Lookup;

use Setono\SyliusTermsPlugin\Model\TermsVersionTranslationInterface;
use Sylius\Component\Channel\Model\ChannelInterface;

/**
 * Finds what a Terms page shows: the Terms version in force now, read in the locale.
 *
 * Returns null in exactly the cases where the Terms page doesn't exist: the Terms is unknown, disabled, not in the
 * channel or has no translation in the locale, no Terms version in force has a translation in the locale, or that
 * translation has no Content. The Terms page and the terms_path() and terms_link() Twig functions all go through it, so
 * a link never points at a missing page.
 *
 * The returned translation holds the name and the Content; its version holds the number, the moment it is in force
 * from and the Terms, whose slug in the locale is the page's address
 */
interface TermsPageLookupInterface
{
    public function lookupBySlug(string $slug, ChannelInterface $channel, string $localeCode): ?TermsVersionTranslationInterface;

    public function lookupByCode(string $code, ChannelInterface $channel, string $localeCode): ?TermsVersionTranslationInterface;
}
