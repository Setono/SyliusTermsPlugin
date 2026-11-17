<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit\Pdf;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Log\LoggerInterface;
use Setono\SyliusTermsPlugin\Generator\SlugGenerator;
use Setono\SyliusTermsPlugin\Model\Terms;
use Setono\SyliusTermsPlugin\Model\TermsVersion;
use Setono\SyliusTermsPlugin\Model\TermsVersionTranslation;
use Setono\SyliusTermsPlugin\Pdf\ImageInlinerInterface;
use Setono\SyliusTermsPlugin\Pdf\InlinedHtml;
use Setono\SyliusTermsPlugin\Pdf\TermsPdfGenerator;
use Setono\SyliusTermsPlugin\Pdf\UrlAbsolutiserInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\PdfGenerationBundle\Core\Renderer\HtmlToPdfRendererInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Twig\Environment;

final class TermsPdfGeneratorTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<Environment> */
    private ObjectProphecy $twig;

    /** @var ObjectProphecy<UrlAbsolutiserInterface> */
    private ObjectProphecy $urlAbsolutiser;

    /** @var ObjectProphecy<ImageInlinerInterface> */
    private ObjectProphecy $imageInliner;

    /** @var ObjectProphecy<HtmlToPdfRendererInterface> */
    private ObjectProphecy $htmlToPdfRenderer;

    /** @var ObjectProphecy<LoggerInterface> */
    private ObjectProphecy $logger;

    private ArrayAdapter $cache;

    private TermsPdfGenerator $generator;

    protected function setUp(): void
    {
        $this->twig = $this->prophesize(Environment::class);
        $this->twig->render(TermsPdfGenerator::TEMPLATE, Argument::type('array'))->willReturn('<html>rendered</html>');

        $this->urlAbsolutiser = $this->prophesize(UrlAbsolutiserInterface::class);
        $this->urlAbsolutiser->absolutise('<html>rendered</html>', Argument::type(ChannelInterface::class))->willReturn('<html>absolute</html>');

        $this->imageInliner = $this->prophesize(ImageInlinerInterface::class);
        $this->imageInliner->inline('<html>absolute</html>')->willReturn(new InlinedHtml('<html>inlined</html>'));

        $this->htmlToPdfRenderer = $this->prophesize(HtmlToPdfRendererInterface::class);
        $this->htmlToPdfRenderer->render('<html>inlined</html>', 'setono_sylius_terms')->willReturn('%PDF-1.7 bytes');

        $this->logger = $this->prophesize(LoggerInterface::class);
        $this->cache = new ArrayAdapter(storeSerialized: false);

        $this->generator = new TermsPdfGenerator(
            $this->twig->reveal(),
            $this->urlAbsolutiser->reveal(),
            $this->imageInliner->reveal(),
            $this->htmlToPdfRenderer->reveal(),
            $this->cache,
            new SlugGenerator(new AsciiSlugger()),
            $this->logger->reveal(),
        );
    }

    #[Test]
    public function it_renders_the_template_with_absolute_urls_and_inlined_images_in_the_terms_context(): void
    {
        $version = self::createVersion();
        $channel = $this->createChannel('FASHION_WEB');

        $pdf = $this->generator->generate($version, 'da_DK', $channel);

        self::assertSame('%PDF-1.7 bytes', $pdf->content);
        self::assertSame('handelsbetingelser-v3.pdf', $pdf->fileName);
        self::assertTrue($pdf->complete);

        $this->twig->render(TermsPdfGenerator::TEMPLATE, [
            'version' => $version,
            'translation' => $version->getTranslations()->get('da_DK'),
            'locale_code' => 'da_DK',
            'channel' => $channel,
        ])->shouldHaveBeenCalledOnce();
        $this->urlAbsolutiser->absolutise('<html>rendered</html>', $channel)->shouldHaveBeenCalledOnce();
        $this->logger->error(Argument::cetera())->shouldNotHaveBeenCalled();
    }

    #[Test]
    #[DataProvider('provideFileNames')]
    public function the_file_name_is_the_version_name_in_the_locale_slugified_plus_the_version_number(
        string $localeCode,
        string $expectedFileName,
    ): void {
        $pdf = $this->generator->generate(self::createVersion(), $localeCode, $this->createChannel('FASHION_WEB'));

        self::assertSame($expectedFileName, $pdf->fileName);
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function provideFileNames(): \Generator
    {
        yield 'Danish' => ['da_DK', 'handelsbetingelser-v3.pdf'];
        yield 'German' => ['de_DE', 'allgemeine-geschaeftsbedingungen-v3.pdf'];
        yield 'English' => ['en_US', 'terms-and-conditions-v3.pdf'];
    }

    #[Test]
    public function a_second_call_with_the_same_version_locale_and_channel_does_not_render_again(): void
    {
        $first = $this->generator->generate(self::createVersion(), 'da_DK', $this->createChannel('FASHION_WEB'));
        $second = $this->generator->generate(self::createVersion(), 'da_DK', $this->createChannel('FASHION_WEB'));

        self::assertEquals($first, $second);
        $this->htmlToPdfRenderer->render(Argument::cetera())->shouldHaveBeenCalledOnce();
        $this->twig->render(Argument::cetera())->shouldHaveBeenCalledOnce();
    }

    #[Test]
    public function the_cache_key_is_the_version_the_locale_and_the_channel(): void
    {
        $this->generator->generate(self::createVersion(id: 7), 'da_DK', $this->createChannel('FASHION_WEB'));
        $this->generator->generate(self::createVersion(id: 8), 'da_DK', $this->createChannel('FASHION_WEB'));
        $this->generator->generate(self::createVersion(id: 7), 'de_DE', $this->createChannel('FASHION_WEB'));
        $this->generator->generate(self::createVersion(id: 7), 'da_DK', $this->createChannel('HOME_WEB'));

        $this->htmlToPdfRenderer->render(Argument::cetera())->shouldHaveBeenCalledTimes(4);
        self::assertSame(array_fill(0, 4, '%PDF-1.7 bytes'), $this->getCachedValues(), 'The pool holds the PDF bytes');
    }

    #[Test]
    public function an_unsaved_version_is_rendered_every_time(): void
    {
        $this->generator->generate(self::createVersion(id: null), 'da_DK', $this->createChannel('FASHION_WEB'));
        $this->generator->generate(self::createVersion(id: null), 'da_DK', $this->createChannel('FASHION_WEB'));

        $this->htmlToPdfRenderer->render(Argument::cetera())->shouldHaveBeenCalledTimes(2);
        self::assertSame([], $this->getCachedValues());
    }

    #[Test]
    public function an_incomplete_pdf_is_returned_but_not_cached_and_an_error_naming_the_image_url_is_logged(): void
    {
        $this->imageInliner->inline('<html>absolute</html>')->willReturn(new InlinedHtml('<html>inlined</html>', [
            'https://cdn.example.com/map.png' => 'HTTP 404 returned for "https://cdn.example.com/map.png".',
        ]));

        $pdf = $this->generator->generate(self::createVersion(), 'da_DK', $this->createChannel('FASHION_WEB'), ['order_number' => '000000042']);

        self::assertSame('%PDF-1.7 bytes', $pdf->content);
        self::assertSame('handelsbetingelser-v3.pdf', $pdf->fileName);
        self::assertFalse($pdf->complete);
        self::assertSame([], $this->getCachedValues());

        // The next call tries the images again
        $this->generator->generate(self::createVersion(), 'da_DK', $this->createChannel('FASHION_WEB'), ['order_number' => '000000043']);

        $this->htmlToPdfRenderer->render(Argument::cetera())->shouldHaveBeenCalledTimes(2);

        foreach (['000000042', '000000043'] as $orderNumber) {
            $this->logger->error(
                'The image https://cdn.example.com/map.png could not be downloaded for the PDF of version 3 of the Terms "terms_and_conditions" (da_DK, FASHION_WEB), so its alt text is shown instead: HTTP 404 returned for "https://cdn.example.com/map.png".',
                [
                    'image_url' => 'https://cdn.example.com/map.png',
                    'terms_code' => 'terms_and_conditions',
                    'version' => 3,
                    'locale_code' => 'da_DK',
                    'channel_code' => 'FASHION_WEB',
                    'order_number' => $orderNumber,
                ],
            )->shouldHaveBeenCalledOnce();
        }
    }

    #[Test]
    public function it_fails_for_a_locale_the_version_has_no_translation_in(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Version 3 of the Terms "terms_and_conditions" has no translation in the locale "fr_FR"');

        $this->generator->generate(self::createVersion(), 'fr_FR', $this->createChannel('FASHION_WEB'));
    }

    /**
     * The array adapter also lists the keys it missed, with null values
     *
     * @return list<mixed>
     */
    private function getCachedValues(): array
    {
        return array_values(array_filter($this->cache->getValues(), static fn (mixed $value): bool => null !== $value));
    }

    private static function createVersion(?int $id = 7): TermsVersion
    {
        $terms = new Terms();
        $terms->setCode('terms_and_conditions');

        $version = new class($id) extends TermsVersion {
            public function __construct(?int $id)
            {
                parent::__construct();

                $this->id = $id;
            }
        };
        $version->setTerms($terms);
        $version->setNumber(3);

        foreach (['da_DK' => 'Handelsbetingelser', 'de_DE' => 'Allgemeine Geschäftsbedingungen', 'en_US' => 'Terms & Conditions'] as $localeCode => $name) {
            $translation = new TermsVersionTranslation();
            $translation->setLocale($localeCode);
            $translation->setName($name);
            $version->addTranslation($translation);
        }

        return $version;
    }

    private function createChannel(string $code): ChannelInterface
    {
        $channel = $this->prophesize(ChannelInterface::class);
        $channel->getCode()->willReturn($code);

        return $channel->reveal();
    }
}
