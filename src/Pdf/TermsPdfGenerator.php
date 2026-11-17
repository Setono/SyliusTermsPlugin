<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Pdf;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Setono\SyliusTermsPlugin\Generator\SlugGeneratorInterface;
use Setono\SyliusTermsPlugin\Model\TermsVersionInterface;
use Setono\SyliusTermsPlugin\Model\TermsVersionTranslationInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\PdfGenerationBundle\Core\Renderer\HtmlToPdfRendererInterface;
use Twig\Environment;

final readonly class TermsPdfGenerator implements TermsPdfGeneratorInterface
{
    /** The sylius_pdf_generation context the PDFs render in. SetonoSyliusTermsExtension::prepend() gives it the dompdf adapter */
    public const CONTEXT = 'setono_sylius_terms';

    /** The cache pool that holds the PDF bytes. SetonoSyliusTermsExtension::prepend() backs it with cache.app */
    public const CACHE_POOL = 'setono_sylius_terms.pdf';

    public const TEMPLATE = '@SetonoSyliusTermsPlugin/pdf/terms.html.twig';

    public function __construct(
        private Environment $twig,
        private UrlAbsolutiserInterface $urlAbsolutiser,
        private ImageInlinerInterface $imageInliner,
        private HtmlToPdfRendererInterface $htmlToPdfRenderer,
        private CacheItemPoolInterface $cache,
        private SlugGeneratorInterface $slugGenerator,
        private LoggerInterface $logger,
    ) {
    }

    public function generate(
        TermsVersionInterface $version,
        string $localeCode,
        ChannelInterface $channel,
        array $logContext = [],
    ): TermsPdf {
        $translation = $version->getTranslations()->get($localeCode);
        if (!$translation instanceof TermsVersionTranslationInterface) {
            throw new \InvalidArgumentException(sprintf(
                'Version %d of the Terms "%s" has no translation in the locale "%s"',
                (int) $version->getNumber(),
                (string) $version->getTerms()?->getCode(),
                $localeCode,
            ));
        }

        // Only versioned data, so the file name of a version never changes
        $fileName = sprintf(
            '%s-v%d.pdf',
            $this->slugGenerator->generate((string) $translation->getName(), $localeCode),
            (int) $version->getNumber(),
        );

        // A version that isn't saved yet has no id to cache it by
        $cacheItem = null === $version->getId() ? null : $this->cache->getItem(self::cacheKey($version, $localeCode, $channel));

        if (null !== $cacheItem && $cacheItem->isHit()) {
            $content = $cacheItem->get();

            if (is_string($content)) {
                return new TermsPdf($content, $fileName);
            }
        }

        $html = $this->twig->render(self::TEMPLATE, [
            'version' => $version,
            'translation' => $translation,
            'locale_code' => $localeCode,
            'channel' => $channel,
        ]);

        $inlinedHtml = $this->imageInliner->inline($this->urlAbsolutiser->absolutise($html, $channel));
        $content = $this->htmlToPdfRenderer->render($inlinedHtml->html, self::CONTEXT);

        foreach ($inlinedHtml->failedImages as $imageUrl => $reason) {
            $this->logger->error(sprintf(
                'The image %s could not be downloaded for the PDF of version %d of the Terms "%s" (%s, %s), so its alt text is shown instead: %s',
                $imageUrl,
                (int) $version->getNumber(),
                (string) $version->getTerms()?->getCode(),
                $localeCode,
                (string) $channel->getCode(),
                $reason,
            ), [
                'image_url' => $imageUrl,
                'terms_code' => $version->getTerms()?->getCode(),
                'version' => $version->getNumber(),
                'locale_code' => $localeCode,
                'channel_code' => $channel->getCode(),
            ] + $logContext);
        }

        // An incomplete PDF isn't cached, so the next call tries the images again
        if (null !== $cacheItem && $inlinedHtml->isComplete()) {
            $this->cache->save($cacheItem->set($content));
        }

        return new TermsPdf($content, $fileName, $inlinedHtml->isComplete());
    }

    /**
     * The channel is part of the key, because it decides the absolute URLs in the PDF
     */
    private static function cacheKey(TermsVersionInterface $version, string $localeCode, ChannelInterface $channel): string
    {
        return hash('xxh128', serialize([$version->getId(), $localeCode, $channel->getCode()]));
    }
}
