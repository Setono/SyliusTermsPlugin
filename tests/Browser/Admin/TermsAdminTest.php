<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Browser\Admin;

use PHPUnit\Framework\Attributes\Test;
use Playwright\Locator\LocatorInterface;
use Playwright\Page\PageInterface;
use Playwright\Symfony\Test\PlaywrightTestCase;
use function Playwright\Testing\expect;
use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Setono\SyliusTermsPlugin\Repository\TermsRepositoryInterface;
use Setono\SyliusTermsPlugin\Tests\Functional\CreatesTermsTrait;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;

final class TermsAdminTest extends PlaywrightTestCase
{
    use CreatesTermsTrait;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var UserRepositoryInterface<AdminUserInterface> $repository */
        $repository = self::getContainer()->get('sylius.repository.admin_user');
        $admin = $repository->findOneBy(['username' => 'sylius']);
        self::assertInstanceOf(AdminUserInterface::class, $admin);

        $this->loginUser($admin, 'admin');
    }

    #[Test]
    public function the_index_lists_the_terms_by_position_and_filters_them(): void
    {
        self::createTerms('cookie_policy', forms: ['checkout_complete'], enabled: false);
        // The pages load the Terms from the database, where Sylius sets the locale its translation is read in
        self::getEntityManager()->clear();

        $page = $this->open('/admin/terms/');

        self::assertSame(
            ['Position', 'Code', 'Name', 'Forms', 'Channels', 'Required', 'Enabled', 'Actions'],
            self::getTexts($page, 'table thead th'),
        );
        self::assertSame(['terms_and_conditions', 'privacy_policy', 'cookie_policy'], self::getListedCodes($page));
        expect($page->locator('table tbody tr')->first())->toContainText('Checkout > Complete order');

        $page->getByText('Filters')->click();
        $page->locator('#criteria_search_value')->fill('Privacy');
        $page->locator('[data-test-filter]')->click();
        $page->waitForURL('**/admin/terms/?criteria*');

        self::assertSame(['privacy_policy'], self::getListedCodes($page));

        $page->locator('[data-test-reset]')->click();
        $page->waitForURL('**/admin/terms/');
        $page->getByText('Filters')->click();
        $page->locator('#criteria_enabled')->selectOption('false');
        $page->locator('[data-test-filter]')->click();
        $page->waitForURL('**/admin/terms/?criteria*');

        self::assertSame(['cookie_policy'], self::getListedCodes($page));
        self::assertSame([], $page->pageErrors(), 'The Terms index threw JavaScript errors');
    }

    #[Test]
    public function an_admin_creates_terms_through_the_three_cards(): void
    {
        $page = $this->open('/admin/terms/new');

        expect(self::getActiveMenuItem($page))->toBeAttached();

        $page->locator('[data-test-code]')->fill('cookie_policy');
        $page->locator('[data-test-position]')->fill('1');
        $page->locator('[data-test-form-code="registration"]')->check();
        $page->locator('[data-test-channel-code="FASHION_WEB"]')->check();

        // The wand fills the slug from the name through a LiveAction
        $page->locator('[data-test-name="en_US"]')->fill('Cookie Policy & Tracking');
        $page->locator('[data-test-generate-slug-button="en_US"]')->click();
        expect($page->locator('[data-test-slug="en_US"]'))->toHaveValue('cookie-policy-and-tracking');
        $page->locator('[data-test-label="en_US"]')->fill('I accept the [link:cookie policy]');

        // The slug left blank is filled from the name when the form is saved
        $page->locator('[data-test-terms-translations-accordion="de_DE"]')->click();
        $page->locator('[data-test-name="de_DE"]')->fill('Datenschutzerklärung für Cookies');
        $page->locator('[data-test-label="de_DE"]')->fill('Ich akzeptiere die [link:Cookie-Richtlinie]');

        // Leaving the field re-renders the Live form. That has to finish first, or it races the submit
        self::changeAndWaitForRerender($page, 'document.querySelector(\'[data-test-label="de_DE"]\').blur()');
        // A re-render leaves the slug blank: only saving fills it
        expect($page->locator('[data-test-slug="de_DE"]'))->toHaveValue('');

        $page->getByRole('button', ['name' => 'Create', 'exact' => true])->click();
        expect($page->locator('[data-test-sylius-flash-message-type="success"]'))->toBeVisible();
        self::assertMatchesRegularExpression('#/admin/terms/\d+/edit$#', $page->url());

        self::assertSame([], $page->pageErrors(), 'The Terms form threw JavaScript errors');
        expect(self::getActiveMenuItem($page))->toBeAttached();

        $terms = self::getTermsRepository()->findOneBy(['code' => 'cookie_policy']);
        self::assertInstanceOf(TermsInterface::class, $terms);
        self::assertSame(1, $terms->getPosition());
        self::assertTrue($terms->isRequired());
        self::assertSame(['registration'], $terms->getForms());
        self::assertSame(['FASHION_WEB'], array_values(array_map(static fn ($channel) => $channel->getCode(), $terms->getChannels()->toArray())));
        self::assertSame('cookie-policy-and-tracking', $terms->getTranslation('en_US')->getSlug());
        // Transliterated for the translation's locale
        self::assertSame('datenschutzerklaerung-fuer-cookies', $terms->getTranslation('de_DE')->getSlug());
    }

    #[Test]
    public function the_code_content_is_rejected_with_the_reason(): void
    {
        $page = $this->open('/admin/terms/new');

        $page->locator('[data-test-code]')->fill('content');
        $page->locator('[data-test-name="en_US"]')->fill('Content');
        $page->locator('[data-test-label="en_US"]')->fill('I accept the [link:content]');
        self::changeAndWaitForRerender($page, 'document.querySelector(\'[data-test-label="en_US"]\').blur()');

        $page->getByRole('button', ['name' => 'Create', 'exact' => true])->click();
        $page->waitForLoadState('networkidle');

        expect($page->locator('[data-test-code] ~ .invalid-feedback'))->toHaveText('The code "content" is reserved for the hooks of the Terms page. Choose another code.');
        self::assertNull(self::getTermsRepository()->findOneBy(['code' => 'content']));
        self::assertSame([], $page->pageErrors(), 'The Terms form threw JavaScript errors');
    }

    #[Test]
    public function a_stale_form_code_shows_flagged_and_unticking_it_removes_it(): void
    {
        $terms = self::createTerms('newsletter_terms', forms: ['checkout_complete', 'newsletter']);
        self::getEntityManager()->clear();

        $page = $this->open(sprintf('/admin/terms/%d/edit', (int) $terms->getId()));

        $stale = $page->locator('[data-test-form-code="newsletter"]');
        expect($stale)->toBeChecked();
        expect($page->locator('label[for="' . (string) $stale->getAttribute('id') . '"]'))->toHaveText('newsletter (not configured)');

        self::changeAndWaitForRerender($page, 'document.querySelector(\'[data-test-form-code="newsletter"]\').click()');
        expect($stale)->not()->toBeChecked();
        $page->locator('[data-test-update-changes-button]')->click();
        expect($page->locator('[data-test-sylius-flash-message-type="success"]'))->toBeVisible();
        expect($page->locator('[data-test-form-code="checkout_complete"]'))->toBeChecked();
        expect($page->locator('[data-test-form-code="newsletter"]'))->toHaveCount(0);

        self::assertSame(['checkout_complete'], self::reloadTerms($terms)->getForms());
    }

    #[Test]
    public function the_delete_confirmation_mentions_versions_and_disabling(): void
    {
        $page = $this->open('/admin/terms/');

        $page->locator('table tbody tr')->first()->locator('[data-bs-toggle="modal"]')->click();

        // Bootstrap moves the modal out of the row when it opens
        $warning = $page->locator('.modal.show [data-test-delete-terms-warning]');
        expect($warning)->toBeVisible();
        expect($warning)->toContainText('also deletes all their versions');
        expect($warning)->toContainText('disable the terms instead');
    }

    /**
     * Opens the page and waits until its scripts have set it up, so clicks reach Bootstrap and the Live components
     */
    private function open(string $path): PageInterface
    {
        $page = $this->visit($path);
        $page->waitForLoadState('networkidle');

        return $page;
    }

    /**
     * Runs the script, which changes a field of the Live form, and waits until the form has re-rendered
     */
    private static function changeAndWaitForRerender(PageInterface $page, string $script): void
    {
        $page->waitForResponse('**/_components/**', ['action' => $script]);
        $page->waitForFunction('() => null === document.querySelector("[data-controller~=live][busy]")');
    }

    /**
     * @return list<string>
     */
    private static function getTexts(PageInterface $page, string $selector): array
    {
        /** @var list<string> $texts */
        $texts = $page->locator($selector)->evaluateAll('elements => elements.map(element => element.textContent.trim())');

        return $texts;
    }

    /**
     * @return list<string>
     */
    private static function getListedCodes(PageInterface $page): array
    {
        return self::getTexts($page, 'table tbody tr td:nth-child(2) code');
    }

    /**
     * The Terms item in the sidebar, if the sidebar marks it as the current page
     */
    private static function getActiveMenuItem(PageInterface $page): LocatorInterface
    {
        return $page->locator('.sidebar a.dropdown-item.active[href$="/admin/terms/"]');
    }

    private static function getTermsRepository(): TermsRepositoryInterface
    {
        self::getEntityManager()->clear();

        /** @var TermsRepositoryInterface $repository */
        $repository = self::getContainer()->get('setono_sylius_terms.repository.terms');

        return $repository;
    }
}
