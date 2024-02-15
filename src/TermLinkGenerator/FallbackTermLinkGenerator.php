<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\TermLinkGenerator;

use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Webmozart\Assert\Assert;

final class FallbackTermLinkGenerator implements TermLinkGeneratorInterface
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public function isApplicable(TermsInterface $terms): bool
    {
        return true;
    }

    public function generate(TermsInterface $terms, ?string $locale = null): string
    {
        $slug = $terms->getTranslation($locale)->getSlug();
        Assert::notEmpty($slug, 'Cannot generate link without a slug.');

        $explanation = $terms->getTranslation($locale)->getExplanation();
        $link = $this->urlGenerator->generate('setono_sylius_terms_show', ['slug' => $slug]);

        return sprintf(
            '<a href="%s" data-generator="fallback">%s</a>',
            $link,
            (string) $explanation,
        );
    }
}
