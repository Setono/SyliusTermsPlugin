<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Browser\Shop;

use PHPUnit\Framework\Attributes\Test;
use Playwright\Page\PageInterface;
use Playwright\Symfony\Test\PlaywrightTestCase;
use function Playwright\Testing\expect;
use Setono\SyliusTermsPlugin\Tests\Functional\CreatesCartTrait;
use Setono\SyliusTermsPlugin\Tests\Functional\CreatesTermsTrait;

/**
 * The baseline Terms: terms_and_conditions is required on checkout complete, privacy_policy on registration and
 * contact. Each test adds an optional Terms on every Form, which goes last
 */
final class TermsCheckboxesTest extends PlaywrightTestCase
{
    use CreatesCartTrait;
    use CreatesTermsTrait;

    protected function setUp(): void
    {
        parent::setUp();

        self::createTerms(
            'newsletter_consent',
            ['checkout_complete', 'registration', 'contact'],
            [self::getChannel('FASHION_WEB')],
            required: false,
        );
    }

    #[Test]
    public function the_checkboxes_render_on_checkout_complete_before_the_place_order_button(): void
    {
        $user = self::getShopUser();
        self::createCartReadyToComplete($user);
        self::getEntityManager()->clear();
        $this->loginUser($user, 'shop');

        $page = $this->open('/en_US/checkout/complete');

        self::assertCheckboxes($page, ['terms_and_conditions' => true, 'newsletter_consent' => false]);
        self::assertBefore($page, '[data-test-terms]', '[data-test-button="confirmation-button"]');
        self::assertSame([], $page->pageErrors(), 'Checkout complete threw JavaScript errors');
    }

    #[Test]
    public function the_checkboxes_render_on_registration_and_contact_before_the_submit_button(): void
    {
        self::getEntityManager()->clear();

        $page = $this->open('/en_US/register');
        self::assertCheckboxes($page, ['privacy_policy' => true, 'newsletter_consent' => false]);
        self::assertBefore($page, '[data-test-terms]', '[data-test-button="register-button"]');
        self::assertSame([], $page->pageErrors(), 'Registration threw JavaScript errors');

        $page = $this->open('/en_US/contact/');
        self::assertCheckboxes($page, ['privacy_policy' => true, 'newsletter_consent' => false]);
        self::assertBefore($page, '[data-test-terms]', '[data-test-button="contact-send"]');
        self::assertSame([], $page->pageErrors(), 'Contact threw JavaScript errors');
    }

    #[Test]
    public function the_error_shows_next_to_an_unticked_required_checkbox(): void
    {
        $user = self::getShopUser();
        self::createCartReadyToComplete($user);
        self::getEntityManager()->clear();
        $this->loginUser($user, 'shop');

        $page = $this->open('/en_US/checkout/complete');
        $page->locator('[data-test-terms-checkbox="newsletter_consent"]')->check();
        $page->locator('[data-test-button="confirmation-button"]')->click();

        $error = $page->locator('div.error:has(> .form-check > [data-test-terms-checkbox="terms_and_conditions"]) [data-test-validation-error]');
        expect($error)->toHaveText('You must accept the terms to continue.');
        self::assertSame(1, $page->locator('[data-test-terms] [data-test-validation-error]')->count(), 'Only the required Terms has an error');
        self::assertTrue($page->locator('[data-test-terms-checkbox="newsletter_consent"]')->isChecked(), 'The answer is kept');
    }

    #[Test]
    public function on_registration_a_ticked_terms_survives_a_live_component_rerender(): void
    {
        self::getEntityManager()->clear();

        $page = $this->open('/en_US/register');

        // Ticking a checkbox changes the Live form, which re-renders
        self::changeAndWaitForRerender($page, 'document.querySelector(\'[data-test-terms-checkbox="privacy_policy"]\').click()');
        self::assertTrue($page->locator('[data-test-terms-checkbox="privacy_policy"]')->isChecked());

        $page->locator('[data-test-first-name]')->fill('Jane');
        self::changeAndWaitForRerender($page, 'document.querySelector(\'[data-test-first-name]\').blur()');

        self::assertTrue($page->locator('[data-test-terms-checkbox="privacy_policy"]')->isChecked());
        self::assertFalse($page->locator('[data-test-terms-checkbox="newsletter_consent"]')->isChecked());
        expect($page->locator('[data-test-first-name]'))->toHaveValue('Jane');
        self::assertSame([], $page->pageErrors(), 'Registration threw JavaScript errors');
    }

    private function open(string $path): PageInterface
    {
        $page = $this->visit($path);
        $page->waitForLoadState('networkidle');

        return $page;
    }

    /**
     * Asserts the checkboxes in order, each with whether it carries the required flag
     *
     * @param array<string, bool> $expected
     */
    private static function assertCheckboxes(PageInterface $page, array $expected): void
    {
        /** @var list<array{string, bool}> $checkboxes */
        $checkboxes = $page->locator('[data-test-terms] [data-test-terms-checkbox]')->evaluateAll(
            'elements => elements.map(element => [element.dataset.testTermsCheckbox, element.required])',
        );

        self::assertSame($expected, array_column($checkboxes, 1, 0));

        foreach (array_keys($expected) as $code) {
            expect($page->locator(sprintf('[data-test-terms-checkbox="%s"]', $code)))->toBeVisible();
        }
    }

    /**
     * Asserts that the first element comes before the second one on the page
     */
    private static function assertBefore(PageInterface $page, string $first, string $second): void
    {
        self::assertTrue($page->evaluate(sprintf(
            '() => Boolean(document.querySelector(%s).compareDocumentPosition(document.querySelector(%s)) & Node.DOCUMENT_POSITION_FOLLOWING)',
            json_encode($first, \JSON_THROW_ON_ERROR),
            json_encode($second, \JSON_THROW_ON_ERROR),
        )));
    }

    /**
     * Runs the script, which changes a field of the Live form, and waits until the form has re-rendered
     */
    private static function changeAndWaitForRerender(PageInterface $page, string $script): void
    {
        $page->waitForResponse('**/_components/**', ['action' => $script]);
        $page->waitForFunction('() => null === document.querySelector("[data-controller~=live][busy]")');
    }
}
