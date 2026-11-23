<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Browser\Admin;

use PHPUnit\Framework\Attributes\Test;
use Playwright\Symfony\Test\PlaywrightTestCase;
use function Playwright\Testing\expect;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;

/**
 * Without the editor's scripts, the drop zone can't take images, so it doesn't offer to
 */
final class EditorScriptsDisabledTest extends PlaywrightTestCase
{
    protected static function getKernelClass(): string
    {
        return EditorScriptsDisabledKernel::class;
    }

    #[Test]
    public function the_upload_hint_is_hidden_and_the_textarea_shows(): void
    {
        /** @var UserRepositoryInterface<AdminUserInterface> $repository */
        $repository = self::getContainer()->get('sylius.repository.admin_user');
        $admin = $repository->findOneBy(['username' => 'sylius']);
        self::assertInstanceOf(AdminUserInterface::class, $admin);
        $this->loginUser($admin, 'admin');

        $page = $this->visit('/admin/terms/new');
        $page->waitForLoadState('networkidle');

        // The stylesheet has loaded, and the element is not defined
        expect($page->locator('link[data-test-terms-content-stylesheet]'))->toHaveCount(1);
        self::assertFalse($page->evaluate('() => undefined !== customElements.get("file-attachment")'));

        expect($page->locator('[data-test-content-upload-hint="en_US"]'))->toBeHidden();
        expect($page->locator('[data-test-content="en_US"]'))->toBeVisible();
        self::assertSame([], $page->pageErrors(), 'The Terms form threw JavaScript errors');
    }
}
