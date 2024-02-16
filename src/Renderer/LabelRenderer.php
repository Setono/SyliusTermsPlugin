<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Renderer;

use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Webmozart\Assert\Assert;

final class LabelRenderer implements LabelRendererInterface
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public function render(TermsInterface $terms): string
    {
        $slug = $terms->getSlug();
        Assert::notNull($slug);

        $label = $terms->getLabel();
        Assert::notNull($label);

        $label = htmlspecialchars($label);

        $link = $this->urlGenerator->generate('setono_sylius_terms_show', ['slug' => $slug]);

        $replaced = (string) preg_replace_callback('/\[link:(.*?)\]/', static fn ($matches): string => sprintf(
            '<a href="%s" target="_blank">%s</a>',
            $link,
            $matches[1],
        ), $label);

        if ($replaced !== $label) {
            return $replaced;
        }

        return sprintf('<a href="%s" target="_blank">%s</a>', $link, $label);
    }
}
