<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Controller\Action;

use Setono\SyliusTermsPlugin\Lookup\TermsPageLookupInterface;
use Setono\SyliusTermsPlugin\Renderer\ContentRendererInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

/**
 * The Terms page, which shows the Terms version in force. There are no pages for past versions
 */
final readonly class ShowTermsAction
{
    public const TEMPLATE = '@SetonoSyliusTermsPlugin/shop/terms/page.html.twig';

    public function __construct(
        private TermsPageLookupInterface $termsPageLookup,
        private ChannelContextInterface $channelContext,
        private LocaleContextInterface $localeContext,
        private ContentRendererInterface $contentRenderer,
        private Environment $twig,
    ) {
    }

    public function __invoke(string $slug): Response
    {
        $translation = $this->termsPageLookup->lookupBySlug(
            $slug,
            $this->channelContext->getChannel(),
            $this->localeContext->getLocaleCode(),
        );

        if (null === $translation) {
            throw new NotFoundHttpException('The terms page does not exist');
        }

        $version = $translation->getVersion();

        return new Response($this->twig->render(self::TEMPLATE, [
            'terms' => $version->getTerms(),
            'version' => $version,
            'translation' => $translation,
            // Rendered once here, since the content and the table of contents both come from it
            'rendered_content' => $this->contentRenderer->render($translation->getContent() ?? ''),
        ]));
    }
}
