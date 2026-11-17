<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Twig;

use Setono\SyliusTermsPlugin\Lookup\TermsPageLookupInterface;
use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Setono\SyliusTermsPlugin\Model\TermsVersionTranslationInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Extension\RuntimeExtensionInterface;
use Webmozart\Assert\Assert;

/**
 * Links to Terms pages, for example from the shop footer. Both functions find the Terms by code in the current channel
 * and locale, and return nothing in exactly the cases where its Terms page doesn't exist (see TermsPageLookupInterface)
 */
final readonly class TermsRuntime implements RuntimeExtensionInterface
{
    public const LINK_TEMPLATE = '@SetonoSyliusTermsPlugin/shop/terms/link.html.twig';

    public function __construct(
        private TermsPageLookupInterface $termsPageLookup,
        private ChannelContextInterface $channelContext,
        private LocaleContextInterface $localeContext,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Returns the path of the Terms page, or null when there is no Terms page to link to
     */
    public function path(string $code): ?string
    {
        $translation = $this->lookup($code);

        return null === $translation ? null : $this->generatePath($translation);
    }

    /**
     * Returns an <a> to the Terms page with the name of the Terms version in force as its text, or an empty string when
     * there is no Terms page to link to. The markup comes from LINK_TEMPLATE, which apps can override
     *
     * @param array<string, string> $attributes HTML attributes for the <a>, such as {class: 'nav-link'}. They are escaped
     */
    public function link(Environment $env, string $code, array $attributes = []): string
    {
        $translation = $this->lookup($code);
        if (null === $translation) {
            return '';
        }

        // Trimmed, so a link can sit in running text without a space after it
        return trim($env->render(self::LINK_TEMPLATE, [
            'path' => $this->generatePath($translation),
            'terms' => self::getTerms($translation),
            'translation' => $translation,
            'attributes' => $attributes,
        ]));
    }

    private function lookup(string $code): ?TermsVersionTranslationInterface
    {
        return $this->termsPageLookup->lookupByCode(
            $code,
            $this->channelContext->getChannel(),
            $this->localeContext->getLocaleCode(),
        );
    }

    /**
     * The slug isn't versioned, so the address is the Terms' current slug in the translation's locale
     */
    private function generatePath(TermsVersionTranslationInterface $translation): string
    {
        $slug = self::getTerms($translation)->getTranslation($translation->getLocale())->getSlug();
        Assert::notNull($slug);

        return $this->urlGenerator->generate('setono_sylius_terms_shop_terms_show', ['slug' => $slug]);
    }

    private static function getTerms(TermsVersionTranslationInterface $translation): TermsInterface
    {
        $terms = $translation->getVersion()->getTerms();
        Assert::notNull($terms);

        return $terms;
    }
}
