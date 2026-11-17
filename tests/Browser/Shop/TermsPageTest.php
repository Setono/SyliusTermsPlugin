<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Browser\Shop;

use PHPUnit\Framework\Attributes\Test;
use Playwright\Page\PageInterface;
use Playwright\Symfony\Test\PlaywrightTestCase;
use function Playwright\Testing\expect;
use Setono\SyliusTermsPlugin\Tests\Functional\CreatesTermsTrait;

/**
 * Most tests use a long Terms, with a wide image, a wide table and four sections, at /en_US/terms/long-terms
 */
final class TermsPageTest extends PlaywrightTestCase
{
    use CreatesTermsTrait;

    #[Test]
    public function on_a_phone_wide_content_stays_in_the_column_and_the_table_scrolls_in_its_own_box(): void
    {
        $page = $this->openLongTerms(375, 812);

        self::assertTrue($page->evaluate('() => document.documentElement.scrollWidth <= document.documentElement.clientWidth'), 'The page doesn\'t scroll sideways');

        $table = $page->locator('[data-test-terms-content] table');
        self::assertTrue($table->evaluate('table => table.scrollWidth > table.clientWidth'), 'The table is wider than its box');

        self::assertGreaterThan(375, $page->locator('[data-test-terms-content] img')->evaluate('image => image.naturalWidth'), 'The image has loaded, and is wider than the screen');

        $column = self::getRight($page, '[data-test-terms-content]');
        self::assertLessThanOrEqual($column, self::getRight($page, '[data-test-terms-content] table'));
        self::assertLessThanOrEqual($column, self::getRight($page, '[data-test-terms-content] img'));

        // Scrolling the table moves its cells, not the page
        $table->evaluate('table => { table.scrollLeft = 200; }');
        self::assertGreaterThan(0, $table->evaluate('table => table.scrollLeft'));
        self::assertSame(0, $page->evaluate('() => window.scrollX'));
        self::assertSame([], $page->pageErrors(), 'The Terms page threw JavaScript errors');
    }

    #[Test]
    public function on_a_phone_the_table_of_contents_is_collapsed_above_the_content_and_opens_on_click(): void
    {
        $page = $this->openLongTerms(375, 812);

        $toc = $page->locator('[data-test-terms-toc-collapsed]');
        expect($toc)->toBeVisible();
        expect($page->locator('[data-test-terms-toc]'))->toBeHidden();
        self::assertFalse($toc->evaluate('details => details.open'));
        expect($toc->locator('a[href="#content-delivery"]'))->toBeHidden();
        self::assertLessThan(self::getTop($page, '[data-test-terms-name]'), self::getTop($page, '[data-test-terms-toc-collapsed]'));

        $toc->locator('summary')->click();

        self::assertTrue($toc->evaluate('details => details.open'));
        expect($toc->locator('a[href="#content-delivery"]'))->toBeVisible();
    }

    #[Test]
    public function on_a_desktop_the_sidebar_stays_in_view_and_an_entry_jumps_to_its_heading(): void
    {
        $page = $this->openLongTerms(1280, 800);

        expect($page->locator('[data-test-terms-toc-collapsed]'))->toBeHidden();
        expect($page->locator('[data-test-terms-toc]'))->toBeVisible();
        self::assertLessThan(self::getLeft($page, '[data-test-terms-content]'), self::getLeft($page, '[data-test-terms-toc]'), 'The sidebar is left of the content');

        $page->locator('[data-test-terms-toc] a[href="#content-delivery"]')->click();

        $page->waitForFunction('() => location.hash === "#content-delivery"');
        // The jump may scroll smoothly, so wait for the heading to arrive near the top
        $page->waitForFunction('() => Math.abs(document.getElementById("content-delivery").getBoundingClientRect().top - 24) < 2');
        self::assertGreaterThan(800, $page->evaluate('() => window.scrollY'), 'The page has scrolled well past the sidebar\'s own place');

        $sidebarTop = self::getTop($page, '[data-test-terms-toc]');
        self::assertGreaterThanOrEqual(0, $sidebarTop);
        self::assertLessThan(100, $sidebarTop, 'The sidebar has stuck to the top');
        expect($page->locator('[data-test-terms-version]'))->toBeVisible();
    }

    #[Test]
    public function in_print_only_the_name_and_the_content_show(): void
    {
        $page = $this->open('/en_US/terms/terms-and-conditions', 1280, 800);

        expect($page->locator('[data-test-menu]'))->toBeVisible();

        $page->emulateMedia(['media' => 'print']);

        expect($page->locator('[data-test-terms-name]'))->toBeVisible();
        expect($page->locator('[data-test-terms-content]'))->toBeVisible();

        foreach (['[data-test-menu]', '[data-test-cart-button]', 'footer', '.breadcrumb', '[data-test-terms-sidebar]'] as $selector) {
            expect($page->locator($selector))->toBeHidden();
        }

        self::assertTrue(
            $page->locator('[data-test-terms-page]')->evaluate('page => [...page.parentElement.children].every(element => element === page || getComputedStyle(element).display === "none")'),
            'Everything around the Terms page container, so the whole header and footer, is hidden',
        );
    }

    #[Test]
    public function the_homepage_footer_link_opens_the_terms_page(): void
    {
        $page = $this->open('/en_US/', 1280, 800);

        $page->locator('footer a.nav-link:has-text("Terms and Conditions")')->click();

        $page->waitForURL('**/en_US/terms/terms-and-conditions');
        expect($page->locator('h1[data-test-terms-name]'))->toHaveText('Terms and Conditions');
        self::assertSame('Terms and Conditions | Fashion Web Store', $page->title());
    }

    private function openLongTerms(int $width, int $height): PageInterface
    {
        $terms = self::createTerms('long_terms', channels: [self::getChannel('FASHION_WEB')]);
        $terms->getTranslation('en_US')->setContent(self::getLongContent());
        self::getEntityManager()->flush();
        self::getEntityManager()->clear();

        return $this->open('/en_US/terms/long-terms', $width, $height);
    }

    private function open(string $path, int $width, int $height): PageInterface
    {
        $page = $this->visit($path);
        $page->setViewportSize($width, $height);
        $page->waitForLoadState('networkidle');

        return $page;
    }

    private static function getTop(PageInterface $page, string $selector): float
    {
        return self::getBox($page, $selector)['y'];
    }

    private static function getLeft(PageInterface $page, string $selector): float
    {
        return self::getBox($page, $selector)['x'];
    }

    private static function getRight(PageInterface $page, string $selector): float
    {
        $box = self::getBox($page, $selector);

        return $box['x'] + $box['width'];
    }

    /**
     * @return array{x: float, y: float, width: float, height: float}
     */
    private static function getBox(PageInterface $page, string $selector): array
    {
        /** @var array{x: float, y: float, width: float, height: float}|null $box */
        $box = $page->locator($selector)->boundingBox();
        self::assertNotNull($box, $selector);

        return $box;
    }

    /**
     * Four sections, each long enough to fill the screen, with a wide image and a wide table in them
     */
    private static function getLongContent(): string
    {
        $paragraphs = static fn (string $topic): string => implode("\n\n", array_map(
            static fn (int $number): string => sprintf('%s, paragraph %d. %s', $topic, $number, str_repeat('These terms explain what you and the shop agree to when you place an order. ', 6)),
            range(1, 6),
        ));

        $table = implode("\n", [
            '| Method | Fee | Charged | Currency | Provider | Settlement | Refunds | Notes |',
            '|---|--:|---|---|---|---|---|---|',
            '| Card | 0.00 | When the order ships | EUR | The card acquirer | Within two business days | Back to the card | Visa, Mastercard and American Express |',
            '| Bank transfer | 0.00 | Before the order ships | EUR | Your bank | Within one business day | Back to the account | Include the order number as the reference |',
        ]);

        return implode("\n\n", [
            'These terms apply to every order placed in this shop.',
            '## Ordering',
            $paragraphs('Ordering'),
            '![The shop\'s warehouse](/images/terms-wide.png)',
            '## Prices and payment',
            $table,
            $paragraphs('Payment'),
            '## Delivery',
            $paragraphs('Delivery'),
            '## Returns',
            $paragraphs('Returns'),
        ]);
    }
}
