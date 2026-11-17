<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Functional\Pdf;

use PHPUnit\Framework\Attributes\Test;
use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Setono\SyliusTermsPlugin\Model\TermsVersionInterface;
use Setono\SyliusTermsPlugin\Pdf\TermsPdfGeneratorInterface;
use Setono\SyliusTermsPlugin\Tests\Application\HttpClient\MockResponseFactory;
use Setono\SyliusTermsPlugin\Tests\Application\Pdf\RecordingHtmlToPdfRenderer;
use Setono\SyliusTermsPlugin\Tests\Functional\CreatesTermsTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * The test environment switches the Terms PDFs on. Its PDF renderer records the HTML it is given and renders it with
 * the real dompdf, and its HTTP client answers from the MockResponseFactory
 */
final class TermsPdfGeneratorTest extends KernelTestCase
{
    use ClockSensitiveTrait;
    use CreatesTermsTrait;

    /** A 1x1 PNG */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        self::bootKernel();

        self::mockTime('2026-11-16 12:00:00');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        restore_exception_handler();
    }

    /**
     * The URLs are http, because Sylius sets SYLIUS_UNSECURED_URLS to yes unless an app sets it to false
     */
    #[Test]
    public function the_html_given_to_the_renderer_has_absolute_urls_inlined_images_the_localised_version_line_and_the_page_css(): void
    {
        $this->getMockResponseFactory()->add('http://shop.example.com/media/image/map.png', new MockResponse(
            (string) base64_decode(self::PNG, true),
            ['response_headers' => ['content-type' => 'image/png']],
        ));

        $version = $this->createVersion('de_DE', 'Lieferbedingungen', <<<'MARKDOWN'
            ## Lieferung

            Fragen? [Kontakt](/de_DE/contact) oder [E-Mail](mailto:info@example.com).

            ![So finden Sie uns](/media/image/map.png)
            MARKDOWN);

        $channel = self::getChannel('FASHION_WEB');
        $channel->setHostname('shop.example.com');

        $pdf = $this->getGenerator()->generate($version, 'de_DE', $channel);

        $renders = $this->getRecordingRenderer()->getRenders();
        self::assertCount(1, $renders);
        self::assertSame('setono_sylius_terms', $renders[0]['context']);

        $html = $renders[0]['html'];
        self::assertStringContainsString('<html lang="de-DE">', $html);
        self::assertStringContainsString('<title>Lieferbedingungen</title>', $html);
        self::assertStringContainsString('<h1>Lieferbedingungen</h1>', $html);
        self::assertStringContainsString('<p class="version">Version 2, in Kraft seit dem 16. November 2026</p>', $html);
        self::assertStringContainsString('<a href="http://shop.example.com/de_DE/contact">Kontakt</a>', $html);
        self::assertStringContainsString('<a href="mailto:info@example.com">E-Mail</a>', $html);
        self::assertStringContainsString(sprintf('<img src="data:image/png;base64,%s" alt="So finden Sie uns">', self::PNG), $html);
        self::assertStringContainsString('size: A4;', $html);
        self::assertStringContainsString('font-family: "DejaVu Sans", sans-serif;', $html);
        self::assertSame(['http://shop.example.com/media/image/map.png'], $this->getMockResponseFactory()->getRequestedUrls());

        self::assertTrue($pdf->complete);
        self::assertSame('lieferbedingungen-v2.pdf', $pdf->fileName);
        self::assertStringStartsWith('%PDF-', $pdf->content);
    }

    #[Test]
    public function the_version_line_follows_the_given_locale_not_the_request_locale(): void
    {
        $version = $this->createVersion('fr_FR', 'Conditions générales de vente', 'Contenu');

        $this->getGenerator()->generate($version, 'fr_FR', self::getChannel('FASHION_WEB'));

        self::assertStringContainsString(
            '<p class="version">Version 2, en vigueur depuis le 16 novembre 2026</p>',
            $this->getRecordingRenderer()->getRenders()[0]['html'],
        );
    }

    #[Test]
    public function dompdf_renders_non_ascii_text_tables_and_nested_lists(): void
    {
        $version = $this->createVersion('da_DK', 'Handelsbetingelser', <<<'MARKDOWN'
            ## Fortrydelsesret

            Du har 14 dages fortrydelsesret. Æbler, øl og ål sendes med fragt — “gratis” over 500 kr.

            | Levering | Pris |
            | -------- | ---: |
            | Pakkeshop | 39 kr. |
            | Hjemmelevering | 59 kr. |

            1. Returnering
               - Pak varen forsvarligt
                 - Vedlæg følgesedlen
               - Send den inden 14 dage
            2. Refusion
            MARKDOWN);

        $pdf = $this->getGenerator()->generate($version, 'da_DK', self::getChannel('FASHION_WEB'));

        self::assertStringStartsWith('%PDF-', $pdf->content);
        self::assertSame('handelsbetingelser-v2.pdf', $pdf->fileName);
        self::assertTrue($pdf->complete);

        $html = $this->getRecordingRenderer()->getRenders()[0]['html'];
        self::assertStringContainsString('Æbler, øl og ål', $html);
        self::assertStringContainsString('<table>', $html);
        self::assertStringContainsString('<li>Vedlæg følgesedlen</li>', $html);
    }

    /**
     * Creates a Terms in the locale, then changes its name and content, which saves version 2
     */
    private function createVersion(string $localeCode, string $name, string $content): TermsVersionInterface
    {
        $terms = self::createTerms('delivery_terms', localeCodes: [$localeCode]);

        $translation = $terms->getTranslation($localeCode);
        $translation->setName($name);
        $translation->setContent($content);
        self::getEntityManager()->flush();

        $version = self::reloadTerms($terms)->getLatestVersion();
        self::assertInstanceOf(TermsVersionInterface::class, $version);
        self::assertInstanceOf(TermsInterface::class, $version->getTerms());
        self::assertSame(2, $version->getNumber());

        return $version;
    }

    private function getGenerator(): TermsPdfGeneratorInterface
    {
        $generator = self::getContainer()->get(TermsPdfGeneratorInterface::class);
        self::assertInstanceOf(TermsPdfGeneratorInterface::class, $generator);

        return $generator;
    }

    private function getRecordingRenderer(): RecordingHtmlToPdfRenderer
    {
        $renderer = self::getContainer()->get(RecordingHtmlToPdfRenderer::class);
        self::assertInstanceOf(RecordingHtmlToPdfRenderer::class, $renderer);

        return $renderer;
    }

    private function getMockResponseFactory(): MockResponseFactory
    {
        $factory = self::getContainer()->get(MockResponseFactory::class);
        self::assertInstanceOf(MockResponseFactory::class, $factory);

        return $factory;
    }
}
