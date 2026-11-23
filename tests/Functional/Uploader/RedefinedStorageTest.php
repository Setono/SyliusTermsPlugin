<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Functional\Uploader;

use PHPUnit\Framework\Attributes\Test;
use Setono\SyliusTermsPlugin\Tests\Functional\StoresImageFixturesTrait;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * An app that defines the storage of Uploaded images in its own config replaces the plugin's, and the URLs in the
 * Content come from the app's storage
 */
final class RedefinedStorageTest extends WebTestCase
{
    use StoresImageFixturesTrait;

    protected static function getKernelClass(): string
    {
        return RedefinedStorageKernel::class;
    }

    protected function setUp(): void
    {
        self::deleteStoredImages();
    }

    protected function tearDown(): void
    {
        self::deleteStoredImages();

        parent::tearDown();

        restore_exception_handler();
    }

    #[Test]
    public function an_upload_is_stored_in_the_apps_storage_and_gets_the_url_it_gives(): void
    {
        $client = self::createClient();

        /** @var UserRepositoryInterface<AdminUserInterface> $repository */
        $repository = self::getContainer()->get('sylius.repository.admin_user');
        $admin = $repository->findOneBy(['username' => 'sylius']);
        self::assertInstanceOf(AdminUserInterface::class, $admin);
        $client->loginUser($admin, 'admin');

        $crawler = $client->request('GET', '/admin/terms/new');
        $token = (string) $crawler->filter('[data-test-content-upload="en_US"]')->attr('data-csrf-token');

        $client->request('POST', '/admin/terms/uploaded-images', ['_csrf_token' => $token], [
            'image' => new UploadedFile(self::FIXTURES . '/cookie.png', 'cookie.png', null, \UPLOAD_ERR_OK, true),
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame(
            sprintf('{"url":"%s"}', addcslashes(RedefinedStorageKernel::PUBLIC_URL . '/' . self::PNG, '/')),
            $client->getResponse()->getContent(),
        );
        self::assertFileEquals(self::FIXTURES . '/cookie.png', RedefinedStorageKernel::DIRECTORY . '/' . self::PNG);
        self::assertNotContains(self::PNG, self::getStoredImages(), 'The plugin\'s storage is replaced, not merged');
    }

    /**
     * Deletes the PNG fixture from the app's storage, and from the plugin's in case it went there
     */
    private static function deleteStoredImages(): void
    {
        self::deleteStoredFixtures();

        if (is_file(RedefinedStorageKernel::DIRECTORY . '/' . self::PNG)) {
            unlink(RedefinedStorageKernel::DIRECTORY . '/' . self::PNG);
        }
    }
}
