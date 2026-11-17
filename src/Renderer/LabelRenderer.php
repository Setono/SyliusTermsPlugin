<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Renderer;

use Setono\SyliusTermsPlugin\Model\TermsVersionTranslationInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Webmozart\Assert\Assert;

final readonly class LabelRenderer implements LabelRendererInterface
{
    private const LINK_PLACEHOLDER = '/\[link:(.*?)\]/';

    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function render(TermsVersionTranslationInterface $translation): string
    {
        $label = $translation->getLabel();
        Assert::notNull($label);

        // The Label is plain text, and the Link placeholder is its only markup
        $label = self::escape($label);

        // Without Content the Terms page is empty, so there is nothing to link to
        $content = $translation->getContent();
        if (null === $content || '' === $content) {
            return (string) preg_replace(self::LINK_PLACEHOLDER, '$1', $label);
        }

        $link = self::escape($this->urlGenerator->generate('setono_sylius_terms_shop_terms_show', [
            'slug' => self::getSlug($translation),
        ]));

        $replaced = (string) preg_replace_callback(self::LINK_PLACEHOLDER, static fn (array $matches): string => sprintf(
            '<a href="%s" target="_blank">%s</a>',
            $link,
            $matches[1],
        ), $label);

        if ($replaced !== $label) {
            return $replaced;
        }

        return sprintf('<a href="%s" target="_blank">%s</a>', $link, $label);
    }

    /**
     * The slug isn't versioned, so it is the Terms' current slug in the translation's locale
     */
    private static function getSlug(TermsVersionTranslationInterface $translation): string
    {
        $terms = $translation->getVersion()->getTerms();
        Assert::notNull($terms);

        $slug = $terms->getTranslation($translation->getLocale())->getSlug();
        Assert::notNull($slug);

        return $slug;
    }

    private static function escape(string $text): string
    {
        return htmlspecialchars($text, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }
}
