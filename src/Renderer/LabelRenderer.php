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
        $label = $terms->getLabel();
        Assert::notNull($label);

        // if the terms has no content, we don't need to create a link because the terms will be empty
        if ($terms->getContent() === null || '' === $terms->getContent()) {
            return $label;
        }

        $slug = $terms->getSlug();
        Assert::notNull($slug);

        $label = htmlspecialchars($label);

        $link = $this->urlGenerator->generate('setono_sylius_terms_shop_show_terms', ['slug' => $slug]);

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
