<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\TermLinkGenerator;

use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Symfony\Component\Routing\RouterInterface;
use Webmozart\Assert\Assert;

final class MarkdownTermLinkGenerator implements TermLinkGeneratorInterface
{
    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @param RouterInterface $router
     */
    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    /**
     * {@inheritdoc}
     */
    public function isApplicable(TermsInterface $terms): bool
    {
        return 1 === preg_match('/\[link\:(.*?)\]/', $terms->getExplanation());
    }

    /**
     * {@inheritdoc}
     */
    public function generate(TermsInterface $terms, ?string $locale = null): string
    {
        $slug = $terms->getTranslation($locale)->getSlug();
        Assert::notEmpty($slug, 'Cannot generate link without a slug.');

        $explanation = $terms->getTranslation($locale)->getExplanation();
        $link = $this->router->generate('setono_sylius_terms_show', ['slug' => $slug]);

        return preg_replace_callback('/\[link\:(.*?)\]/', function ($matches) use ($link){
            return sprintf(
                '<a href="%s" data-generator="markdown">%s</a>',
                $link,
                $matches[1]
            );
        }, htmlspecialchars($explanation));
    }
}
