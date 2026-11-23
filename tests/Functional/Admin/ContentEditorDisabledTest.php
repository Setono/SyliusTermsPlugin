<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Functional\Admin;

use PHPUnit\Framework\Attributes\Test;
use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Setono\SyliusTermsPlugin\Repository\TermsRepositoryInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Disabling the content editor's hookables in sylius_twig_hooks config leaves the Content a plain textarea
 */
final class ContentEditorDisabledTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return ContentEditorDisabledKernel::class;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        restore_exception_handler();
    }

    #[Test]
    public function the_form_pages_have_no_scripts_toolbar_drop_zone_or_tabs(): void
    {
        $client = self::createClient();

        /** @var UserRepositoryInterface<AdminUserInterface> $userRepository */
        $userRepository = self::getContainer()->get('sylius.repository.admin_user');
        $admin = $userRepository->findOneBy(['username' => 'sylius']);
        self::assertInstanceOf(AdminUserInterface::class, $admin);
        $client->loginUser($admin, 'admin');

        /** @var TermsRepositoryInterface $termsRepository */
        $termsRepository = self::getContainer()->get('setono_sylius_terms.repository.terms');
        $terms = $termsRepository->findOneBy(['code' => 'terms_and_conditions']);
        self::assertInstanceOf(TermsInterface::class, $terms);

        foreach (['/admin/terms/new', sprintf('/admin/terms/%d/edit', (int) $terms->getId())] as $path) {
            $crawler = $client->request('GET', $path);

            self::assertResponseIsSuccessful();
            self::assertCount(0, $crawler->filter('script[data-test-markdown-toolbar-script], script[data-test-file-attachment-script], script[data-test-upload-images-script]'), $path);
            self::assertCount(0, $crawler->filter('markdown-toolbar'), $path);
            self::assertCount(0, $crawler->filter('file-attachment, [data-test-content-upload-hint], input[type="file"]'), $path);
            self::assertCount(0, $crawler->filter('[data-test-content-preview-tab], [data-test-content-preview]'), $path);

            // The textarea is left, in the only pane, which is shown
            $textarea = $crawler->filter('textarea[data-test-content="en_US"]');
            self::assertCount(1, $textarea, $path);
            self::assertStringContainsString('active', (string) $textarea->ancestors()->filter('.tab-pane')->attr('class'));
        }
    }
}
