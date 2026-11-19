<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Browser;

use PHPUnit\Framework\Attributes\Test;
use Playwright\Page\PageInterface;
use Playwright\Symfony\Test\PlaywrightTestCase;
use function Playwright\Testing\expect;
use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Setono\SyliusTermsPlugin\Tests\Functional\CreatesCartTrait;
use Setono\SyliusTermsPlugin\Tests\Functional\CreatesTermsTrait;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

/**
 * The admin Terms pages, the Terms page and the Terms checkboxes in Polish, one of the locales the plugin translates
 * into: each shows the plugin's Polish texts and no raw translation key. The translator falls back to English, so a
 * key missing from one locale's file shows in English instead of raw. The unit suite's TranslationsTest catches that
 */
final class PolishTranslationsTest extends PlaywrightTestCase
{
    use CreatesCartTrait;
    use CreatesTermsTrait;

    private const LOCALE = 'pl_PL';

    #[Test]
    public function the_admin_terms_index_is_in_polish(): void
    {
        self::createTerms('cookie_policy', forms: ['checkout_complete']);
        self::getEntityManager()->clear();
        $this->loginAdmin();

        $page = $this->open('/admin/terms/');

        expect($page->locator('h1.page-title'))->toHaveText('Warunki');
        expect($page->locator('h1.page-title + div'))->toHaveText('Zarządzaj warunkami, które klienci akceptują w formularzach Twojego sklepu');
        expect($page->locator('.sidebar a.dropdown-item[href$="/admin/terms/"]'))->toHaveText('Warunki');
        self::assertSame(
            ['Pozycja', 'Kod', 'Nazwa', 'Formularze', 'Kanały', 'Wymagane', 'Włączone', 'Akcje'],
            self::getTexts($page, 'table thead th'),
        );
        expect($page->locator('table tbody tr:has-text("cookie_policy")'))->toContainText('Kasa > Złóż zamówienie');

        $page->locator('table tbody tr:has-text("cookie_policy") [data-bs-toggle="modal"]')->click();
        expect($page->locator('.modal.show [data-test-delete-terms-warning]'))->toHaveText(
            'Usunięcie warunków usuwa również wszystkie ich wersje. Aby zachować historię wersji, wyłącz warunki zamiast je usuwać.',
        );

        self::assertNoRawTranslationKeys($page);
    }

    #[Test]
    public function the_admin_create_page_and_its_validation_are_in_polish(): void
    {
        $this->loginAdmin();

        $page = $this->open('/admin/terms/new');

        expect($page->locator('h1.page-title'))->toHaveText('Nowe warunki');
        expect($page->getByText('Umiejscowienie', ['exact' => true]))->toBeVisible();
        expect($page->getByText('Pozostaw puste, aby umieścić warunki na końcu.', ['exact' => false]))->toBeVisible();
        expect($page->locator('[data-test-generate-slug-button="en_US"]'))->toHaveAttribute('title', 'Utwórz slug z nazwy');
        expect($page->locator('[data-test-markdown-toolbar="en_US"]'))->toHaveAttribute('aria-label', 'Formatowanie');
        expect($page->locator('[data-test-markdown-toolbar="en_US"] [data-test-markdown-button="bulleted_list"]'))->toHaveAttribute('title', 'Lista punktowana');
        expect($page->locator('[data-test-content-write-tab="en_US"]'))->toHaveText('Edycja');
        expect($page->locator('[data-test-content-preview-tab="en_US"]'))->toHaveText('Podgląd');
        self::assertNoRawTranslationKeys($page);

        $page->locator('[data-test-code]')->fill('content');
        self::changeAndWaitForRerender($page, 'document.querySelector(\'[data-test-code]\').blur()');
        $page->getByRole('button', ['name' => 'Utwórz', 'exact' => true])->click();
        // The re-render already shows the code's error, so wait for the page the server rejected the form with
        $page->waitForFunction('() => 422 === performance.getEntriesByType("navigation")[0].responseStatus');
        $page->waitForLoadState('networkidle');

        // The validators domain. The other fields' errors come from Sylius' and Symfony's translations
        expect($page->locator('[data-test-code] ~ .invalid-feedback'))->toHaveText('Kod "content" jest zarezerwowany dla hooków strony warunków. Wybierz inny kod.');
        self::assertNoRawTranslationKeys($page);
    }

    #[Test]
    public function the_admin_update_and_version_pages_are_in_polish(): void
    {
        $terms = self::createTerms('cookie_policy', forms: ['checkout_complete']);
        self::getEntityManager()->clear();
        $this->loginAdmin();

        $page = $this->open(sprintf('/admin/terms/%d/edit', (int) $terms->getId()));

        expect($page->locator('h1.page-title'))->toHaveText('Edytuj warunki');
        expect($page->locator('[data-test-terms-versions]'))->toContainText('Wersje');
        self::assertSame(['Numer', 'Obowiązuje od'], array_slice(self::getTexts($page, '[data-test-terms-versions] thead th'), 0, 2));
        self::assertNoRawTranslationKeys($page);

        $page = $this->open(sprintf('/admin/terms/%d/versions/1', (int) $terms->getId()));

        expect($page->locator('h1.page-title'))->toHaveText('Wersja 1');
        self::assertMatchesRegularExpression('/^Obowiązuje od \d{1,2} \p{L}+ \d{4}$/u', self::getText($page, 'h1.page-title + div'));
        expect($page->getByText('Tekst przy polu wyboru', ['exact' => true]))->toBeVisible();
        expect($page->locator('[data-test-content-source-tab="en_US"]'))->toHaveText('Źródło');
        self::assertNoRawTranslationKeys($page);
    }

    #[Test]
    public function the_terms_page_is_in_polish(): void
    {
        $terms = self::createPolishTerms();
        $terms->getTranslation(self::LOCALE)->setContent("## Zamówienia\n\nZamówienie składasz w sklepie.\n\n## Dostawa\n\nWysyłamy w ciągu dwóch dni.");
        self::getEntityManager()->flush();
        self::getEntityManager()->clear();

        $page = $this->open('/pl_PL/terms/cookie-policy');

        expect($page->locator('[data-test-terms-name]'))->toHaveText('Polityka cookies');
        expect($page->locator('[data-test-terms-toc]'))->toContainText('Na tej stronie');
        self::assertMatchesRegularExpression('/^Wersja \d+\s+Obowiązuje od \d{1,2} \p{L}+ \d{4}$/u', self::getText($page, '[data-test-terms-version]'));
        self::assertNoRawTranslationKeys($page);
    }

    #[Test]
    public function the_checkboxes_and_their_error_are_in_polish(): void
    {
        self::createPolishTerms();
        $user = self::getShopUser();
        self::createCartReadyToComplete($user);
        self::getEntityManager()->clear();
        $this->loginUser($user, 'shop');

        $page = $this->open('/pl_PL/checkout/complete');

        // The baseline Terms have no Polish translation, so they don't show
        expect($page->locator('[data-test-terms] [data-test-terms-checkbox]'))->toHaveCount(1);
        expect($page->locator('[data-test-terms] label'))->toHaveText('Akceptuję politykę cookies');

        $page->locator('[data-test-button="confirmation-button"]')->click();

        // The validators domain
        expect($page->locator('[data-test-terms] [data-test-validation-error]'))->toHaveText('Musisz zaakceptować warunki, aby kontynuować.');
        self::assertNoRawTranslationKeys($page);
    }

    /**
     * Logs in Sylius' admin, whose locale the admin pages are in
     */
    private function loginAdmin(): void
    {
        /** @var UserRepositoryInterface<AdminUserInterface> $repository */
        $repository = self::getContainer()->get('sylius.repository.admin_user');
        $admin = $repository->findOneBy(['username' => 'sylius']);
        self::assertInstanceOf(AdminUserInterface::class, $admin);

        $admin->setLocaleCode(self::LOCALE);
        self::getEntityManager()->flush();

        $this->loginUser($admin, 'admin');
    }

    /**
     * Adds Polish, which Sylius' default fixtures create, to FASHION_WEB, and creates a required Terms on checkout
     * complete with a Polish translation
     */
    private static function createPolishTerms(): TermsInterface
    {
        /** @var RepositoryInterface<LocaleInterface> $repository */
        $repository = self::getContainer()->get('sylius.repository.locale');
        $locale = $repository->findOneBy(['code' => self::LOCALE]);
        self::assertInstanceOf(LocaleInterface::class, $locale);
        self::getChannel('FASHION_WEB')->addLocale($locale);

        $terms = self::createTerms('cookie_policy', ['checkout_complete'], [self::getChannel('FASHION_WEB')], ['en_US', self::LOCALE]);
        $translation = $terms->getTranslation(self::LOCALE);
        $translation->setName('Polityka cookies');
        $translation->setLabel('Akceptuję [link:politykę cookies]');
        self::getEntityManager()->flush();

        return $terms;
    }

    /**
     * Asserts that no text on the page, including hidden text and the attributes people read, is a translation key
     */
    private static function assertNoRawTranslationKeys(PageInterface $page): void
    {
        /** @var list<string> $keys */
        $keys = $page->evaluate(<<<'JS'
            () => {
                const body = document.body.cloneNode(true);
                body.querySelectorAll('script, style, noscript, template').forEach(element => element.remove());

                const texts = [document.title, body.textContent];
                for (const attribute of ['title', 'aria-label', 'placeholder', 'alt', 'data-bs-title', 'value']) {
                    document.querySelectorAll(`[${attribute}]:not(input[type="hidden"]):not(textarea)`).forEach(element => texts.push(element.getAttribute(attribute)));
                }

                return [...new Set(texts.join('\n').match(/\b(?:setono_sylius_terms|sylius)\.[a-z0-9_]+\.[a-z0-9_.]+/g) ?? [])];
            }
            JS);

        self::assertSame([], $keys, sprintf('%s shows raw translation keys', $page->url()));
        self::assertSame([], $page->pageErrors(), sprintf('%s threw JavaScript errors', $page->url()));
    }

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

    private static function getText(PageInterface $page, string $selector): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $page->locator($selector)->textContent()));
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
}
