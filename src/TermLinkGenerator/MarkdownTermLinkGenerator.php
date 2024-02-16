<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\TermLinkGenerator;

use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Symfony\Component\Routing\RouterInterface;
use Webmozart\Assert\Assert;

final class MarkdownTermLinkGenerator implements TermLinkGeneratorInterface
{
    public function __construct(private readonly RouterInterface $router)
    {
    }

    public function isApplicable(TermsInterface $terms): bool
    {
        return preg_match('/\[link:(.*?)\]/', (string) $terms->getLabel()) === 1;
    }

    public function generate(TermsInterface $terms, ?string $locale = null): string
    {
        $slug = $terms->getTranslation($locale)->getSlug();
        Assert::notEmpty($slug, 'Cannot generate link without a slug.');

        $label = (string) $terms->getTranslation($locale)->getLabel();
        $link = $this->router->generate('setono_sylius_terms_show', ['slug' => $slug]);

        return (string) preg_replace_callback('/\[link:(.*?)\]/', fn ($matches): string => sprintf(
            '<a href="%s" data-generator="markdown">%s</a>',
            $link,
            $matches[1],
        ), htmlspecialchars($label));
    }
}
