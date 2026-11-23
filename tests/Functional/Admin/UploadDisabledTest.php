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
 * Disabling the upload hookable in sylius_twig_hooks config turns off uploading images into the Content, and leaves the
 * rest of the editor as it is
 */
final class UploadDisabledTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return UploadDisabledKernel::class;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        restore_exception_handler();
    }

    #[Test]
    public function the_form_pages_have_the_toolbar_and_the_tabs_but_no_drop_zone(): void
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
            self::assertCount(0, $crawler->filter('file-attachment, [data-test-content-upload-hint], input[type="file"]'), $path);

            // The textarea is rendered once, in the Write pane, below the toolbar
            $textarea = $crawler->filter('textarea[data-test-content="en_US"]');
            self::assertCount(1, $textarea, $path);
            $id = (string) $textarea->attr('id');
            self::assertSame($id . '-write', $textarea->ancestors()->filter('.tab-pane')->attr('id'));
            self::assertSame($id, $crawler->filter('markdown-toolbar[data-test-markdown-toolbar="en_US"]')->attr('for'));
            self::assertCount(1, $crawler->filter('[data-test-content-preview-tab="en_US"]'), $path);
            self::assertCount(1, $crawler->filter('script[data-test-markdown-toolbar-script]'), $path);
        }
    }
}
