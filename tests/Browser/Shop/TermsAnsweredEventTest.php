<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Browser\Shop;

use PHPUnit\Framework\Attributes\Test;
use Playwright\Page\PageInterface;
use Playwright\Symfony\Test\PlaywrightTestCase;
use Setono\SyliusTermsPlugin\Event\TermsAnsweredEvent;
use Setono\SyliusTermsPlugin\Tests\Application\EventListener\TermsAnsweredEventRecorder;

/**
 * The baseline Terms: privacy_policy is required on registration. The test app records each TermsAnsweredEvent. Every
 * request of a browser test is handled by the test's own kernel, so the recorder holds the events of all of them
 */
final class TermsAnsweredEventTest extends PlaywrightTestCase
{
    #[Test]
    public function on_registration_a_live_component_rerender_dispatches_no_event(): void
    {
        $page = $this->visit('/en_US/register');
        $page->waitForLoadState('networkidle');

        $page->locator('[data-test-first-name]')->fill('Jane');
        $page->locator('[data-test-last-name]')->fill('Doe');
        $page->locator('[data-test-email]')->fill('jane.doe@example.com');
        $page->locator('[data-test-password-first]')->fill('secret-password');
        $page->locator('[data-test-password-second]')->fill('secret-password');

        // Waits for the re-renders the filled fields trigger, while the form is still invalid
        self::changeAndWaitForRerender($page, 'document.querySelector(\'[data-test-password-second]\').blur()');

        // Ticking the required Terms, the last field left, re-renders the Live form, which submits it with every field
        // valid. The component answers an invalid form with 422
        $status = self::changeAndWaitForRerender($page, 'document.querySelector(\'[data-test-terms-checkbox="privacy_policy"]\').click()');
        self::assertSame(200, $status, 'The re-render submitted a valid form');
        self::assertSame([], self::getRecorded(), 'The re-render dispatched no event');

        $page->locator('[data-test-button="register-button"]')->click();
        $page->waitForURL('**/register/thank-you?*');

        $recorded = self::getRecorded();
        self::assertCount(1, $recorded, 'The submit dispatched one event');
        self::assertSame('registration', $recorded[0]['event']->form);
        self::assertNotNull($recorded[0]['id'], 'The customer had an ID when the event was dispatched');
        self::assertSame([], $page->pageErrors(), 'Registration threw JavaScript errors');
    }

    /**
     * Runs the script, which changes a field of the Live form, waits until the form has re-rendered and returns the
     * status of the re-render's response
     */
    private static function changeAndWaitForRerender(PageInterface $page, string $script): int
    {
        $response = $page->waitForResponse('**/_components/**', ['action' => $script]);
        $page->waitForFunction('() => null === document.querySelector("[data-controller~=live][busy]")');

        return $response->status();
    }

    /**
     * @return list<array{event: TermsAnsweredEvent, id: mixed, storedCheckoutState: string|null}>
     *
     * @phpstan-impure each request may record more
     */
    private static function getRecorded(): array
    {
        $recorder = self::getContainer()->get(TermsAnsweredEventRecorder::class);
        self::assertInstanceOf(TermsAnsweredEventRecorder::class, $recorder);

        return $recorder->getRecorded();
    }
}
