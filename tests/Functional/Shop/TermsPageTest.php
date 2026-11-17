<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Functional\Shop;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Setono\SyliusTermsPlugin\Tests\Functional\CreatesTermsTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The baseline terms_and_conditions has the slug terms-and-conditions and three ## headings. Each Terms the tests create
 * has the slug of its code, with dashes for underscores
 */
final class TermsPageTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use CreatesTermsTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        restore_exception_handler();
    }

    #[Test]
    public function it_shows_the_name_and_content_of_the_version_in_force_with_its_number_and_date(): void
    {
        self::mockTime('2026-11-16 12:00:00');
        $terms = self::createTerms('shipping', channels: [self::getChannel('FASHION_WEB')]);

        // Version 2 is saved now, but in force only from tomorrow, so the page still shows version 1
        self::mockTime('2026-11-18 09:00:00');
        $translation = $terms->getTranslation('en_US');
        $translation->setName('Shipping, version 2');
        $translation->setContent('The content of version 2');
        self::getEntityManager()->flush();
        self::mockTime('2026-11-17 12:00:00');

        $crawler = $this->client->request('GET', '/en_US/terms/shipping');

        self::assertResponseIsSuccessful();
        self::assertSame('shipping (en_US) | Fashion Web Store', $crawler->filter('title')->text());
        self::assertSame('shipping (en_US)', $crawler->filter('h1[data-test-terms-name]')->text());
        self::assertSame('The content of shipping', $crawler->filter('[data-test-terms-content]')->text());
        self::assertSame('Version 1 In force since November 16, 2026', $crawler->filter('[data-test-terms-version]')->text());

        self::assertSame('Home', $crawler->filter('[data-test-breadcrumb-home]')->text());
        self::assertSame('/en_US/', $crawler->filter('[data-test-breadcrumb-home]')->attr('href'));
        self::assertSame('shipping (en_US)', $crawler->filter('[data-test-breadcrumb-terms]')->text());

        // The breadcrumbs come first, then the sidebar, which sits above the main column on narrow screens
        self::assertSame(
            ['breadcrumb-home', 'breadcrumb-terms', 'terms-version', 'terms-name', 'terms-content'],
            self::testAttributes($crawler->filter('[data-test-terms-page]')),
        );
    }

    #[Test]
    public function the_version_date_is_localised(): void
    {
        self::mockTime('2026-11-16 12:00:00');
        self::createTerms('shipping', channels: [self::getChannel('FASHION_WEB')], localeCodes: ['en_US', 'de_DE']);

        $crawler = $this->client->request('GET', '/de_DE/terms/shipping');

        self::assertResponseIsSuccessful();
        self::assertSame('shipping (de_DE)', $crawler->filter('h1[data-test-terms-name]')->text());
        self::assertSame('Version 1 In Kraft seit dem 16. November 2026', $crawler->filter('[data-test-terms-version]')->text());
    }

    #[Test]
    public function it_renders_markdown_content_and_neutralises_an_xss_payload(): void
    {
        $terms = self::createTerms('scripted', channels: [self::getChannel('FASHION_WEB')]);
        $terms->getTranslation('en_US')->setContent(<<<'MARKDOWN'
            ## Payment

            Prices include **VAT**.

            <script>alert('script')</script>
            <img src="/images/terms-wide.png" alt="Warehouse" onerror="alert('onerror')">

            [Pay here](javascript:alert('link')) or <a href="https://example.com/" onclick="alert('onclick')" class="btn" style="color: red">read more</a>
            MARKDOWN);
        self::getEntityManager()->flush();

        $crawler = $this->client->request('GET', '/en_US/terms/scripted');

        self::assertResponseIsSuccessful();
        $content = $crawler->filter('[data-test-terms-content]');
        self::assertSame('Payment', $content->filter('h2#content-payment')->getNode(0)?->firstChild?->textContent);
        self::assertSame('VAT', $content->filter('p > strong')->text());
        self::assertSame('Warehouse', $content->filter('img')->attr('alt'));
        self::assertSame('https://example.com/', $content->filter('a:not([title="Permalink"])')->last()->attr('href'));

        $html = $content->html();
        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('alert(', $html);
        self::assertStringNotContainsString('javascript:', $html);
        self::assertStringNotContainsString('class=', $html);
        self::assertStringNotContainsString('style=', $html);
    }

    #[Test]
    public function the_table_of_contents_links_to_the_section_headings(): void
    {
        $crawler = $this->client->request('GET', '/en_US/terms/terms-and-conditions');

        self::assertResponseIsSuccessful();

        $headings = [
            ['#content-ordering', 'Ordering'],
            ['#content-prices-and-payment', 'Prices and payment'],
            ['#content-returns', 'Returns'],
        ];

        foreach (['[data-test-terms-toc]', '[data-test-terms-toc-collapsed]'] as $selector) {
            self::assertSame($headings, $crawler->filter($selector . ' a')->extract(['href', '_text']), $selector);
        }

        self::assertSame('details', $crawler->filter('[data-test-terms-toc-collapsed]')->nodeName());
        self::assertNull($crawler->filter('[data-test-terms-toc-collapsed]')->attr('open'), 'The table of contents is collapsed on narrow screens');
        self::assertSame('On this page', $crawler->filter('[data-test-terms-toc-collapsed] summary')->text());

        foreach ($headings as [$id]) {
            self::assertCount(1, $crawler->filter('[data-test-terms-content] h2' . $id), $id);
        }
    }

    #[Test]
    #[DataProvider('provideContentWithFewerThanTwoSections')]
    public function there_is_no_table_of_contents_with_fewer_than_two_sections(string $content): void
    {
        $terms = self::createTerms('short', channels: [self::getChannel('FASHION_WEB')]);
        $terms->getTranslation('en_US')->setContent($content);
        self::getEntityManager()->flush();

        $crawler = $this->client->request('GET', '/en_US/terms/short');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('[data-test-terms-toc], [data-test-terms-toc-collapsed]'));
        self::assertCount(1, $crawler->filter('[data-test-terms-version]'), 'The sidebar still shows the version');
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function provideContentWithFewerThanTwoSections(): \Generator
    {
        yield 'no headings' => ["Short terms.\n\nNothing more."];
        yield 'one section, and other levels' => ["# Title\n\n## Only section\n\n### Subsection\n\nText."];
    }

    /**
     * @param \Closure(): string $createPath creates what the test needs and returns the path to request
     */
    #[Test]
    #[DataProvider('provideMissingPages')]
    public function the_page_is_not_found(\Closure $createPath): void
    {
        $this->client->request('GET', $createPath());

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @return \Generator<string, array{\Closure(): string}>
     */
    public static function provideMissingPages(): \Generator
    {
        yield 'unknown slug' => [static fn (): string => '/en_US/terms/unknown'];

        yield 'disabled Terms' => [static function (): string {
            self::createTerms('disabled', channels: [self::getChannel('FASHION_WEB')], enabled: false);

            return '/en_US/terms/disabled';
        }];

        yield 'Terms not in the channel' => [static function (): string {
            self::createTerms('in_another_channel', channels: [self::createChannel('OTHER_WEB')]);

            return '/en_US/terms/in-another-channel';
        }];

        // The baseline Terms are translated into en_US and de_DE only
        yield 'locale with no translation' => [static fn (): string => '/fr_FR/terms/terms-and-conditions'];

        yield 'no version in force' => [static function (): string {
            self::mockTime('2026-11-18 12:00:00');
            self::createTerms('upcoming', channels: [self::getChannel('FASHION_WEB')]);
            self::mockTime('2026-11-17 12:00:00');

            return '/en_US/terms/upcoming';
        }];
    }

    #[Test]
    public function a_per_code_hookable_changes_the_page_of_that_terms_only(): void
    {
        // The test app adds a notice to the page of hooked_terms and switches its version off there
        self::createTerms('hooked_terms', channels: [self::getChannel('FASHION_WEB')]);

        $crawler = $this->client->request('GET', '/en_US/terms/hooked-terms');

        self::assertResponseIsSuccessful();
        self::assertSame(['breadcrumb-home', 'breadcrumb-terms', 'terms-name', 'terms-notice', 'terms-content'], self::testAttributes($crawler->filter('[data-test-terms-page]')));
        self::assertSame('This notice shows on the Terms page of hooked_terms only.', $crawler->filter('[data-test-terms-notice]')->text());

        $crawler = $this->client->request('GET', '/en_US/terms/terms-and-conditions');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('[data-test-terms-notice]'));
        self::assertCount(1, $crawler->filter('[data-test-terms-version]'));
    }

    #[Test]
    public function the_content_stylesheet_is_linked_on_terms_pages_only(): void
    {
        $stylesheet = 'link[rel="stylesheet"][href="/bundles/setonosyliustermsplugin/shop/terms-page.css"]';

        $crawler = $this->client->request('GET', '/en_US/terms/terms-and-conditions');
        self::assertCount(1, $crawler->filter('head ' . $stylesheet));

        $crawler = $this->client->request('GET', '/en_US/');
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter($stylesheet));
    }

    #[Test]
    public function the_homepage_footer_links_to_the_terms_pages(): void
    {
        $crawler = $this->client->request('GET', '/en_US/');

        self::assertResponseIsSuccessful();

        $links = $crawler->filter('footer a.nav-link[href^="/en_US/terms/"]');
        self::assertSame([
            ['/en_US/terms/terms-and-conditions', 'Terms and Conditions'],
            ['/en_US/terms/privacy-policy', 'Privacy Policy'],
        ], $links->extract(['href', '_text']));
        self::assertCount(0, $crawler->filter('footer a[href="#"]:contains("Terms and Conditions"), footer a[href="#"]:contains("Privacy Policy")'), 'Sylius\' placeholders are replaced');

        $crawler = $this->client->request('GET', '/de_DE/');
        self::assertSame('/de_DE/terms/allgemeine-geschaeftsbedingungen', $crawler->filter('footer a.nav-link:contains("Allgemeine Geschäftsbedingungen")')->attr('href'));
    }

    /**
     * The data-test-* attributes in the element, in document order, of the elements the Terms page tests look at
     *
     * @return list<string>
     */
    private static function testAttributes(Crawler $element): array
    {
        $names = [];

        foreach ($element->filter('[data-test-breadcrumb-home], [data-test-breadcrumb-terms], [data-test-terms-version], [data-test-terms-name], [data-test-terms-notice], [data-test-terms-content]') as $node) {
            self::assertInstanceOf(\DOMElement::class, $node);

            foreach ($node->attributes as $attribute) {
                if (str_starts_with($attribute->name, 'data-test-')) {
                    $names[] = substr($attribute->name, strlen('data-test-'));
                }
            }
        }

        return $names;
    }
}
