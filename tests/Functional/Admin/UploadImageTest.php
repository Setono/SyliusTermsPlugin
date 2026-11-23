<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Functional\Admin;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Setono\SyliusTermsPlugin\Tests\Functional\StoresImageFixturesTrait;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * The endpoint the Content editor uploads images to. The test application keeps the plugin's default storage, so
 * Uploaded images land in its public/media/terms
 */
final class UploadImageTest extends WebTestCase
{
    use StoresImageFixturesTrait;

    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        self::deleteStoredFixtures();
    }

    protected function tearDown(): void
    {
        self::deleteStoredFixtures();
        array_map(unlink(...), $this->temporaryFiles);

        parent::tearDown();

        restore_exception_handler();
    }

    #[Test]
    public function the_plugin_registers_its_storage_of_uploaded_images_served_from_media_terms(): void
    {
        self::bootKernel();

        $storage = self::getContainer()->get('setono_sylius_terms.uploaded_image_storage');

        self::assertInstanceOf(FilesystemOperator::class, $storage);
        self::assertSame('/media/terms/' . self::PNG, $storage->publicUrl(self::PNG));
    }

    #[Test]
    public function an_admin_uploads_an_image_and_gets_the_url_of_the_stored_image(): void
    {
        $client = self::createAdminClient();

        self::upload($client, self::FIXTURES . '/cookie.png', 'cookie.png', self::getCsrfToken($client));

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');
        self::assertSame(['url' => '/media/terms/' . self::PNG], self::getJson($client));
        // Stored as uploaded, byte for byte
        self::assertFileEquals(self::FIXTURES . '/cookie.png', self::STORAGE_DIRECTORY . '/' . self::PNG);
    }

    /**
     * The name the image is stored under comes from its bytes: their hash and the type they show
     */
    #[Test]
    public function a_jpeg_sent_as_a_png_is_stored_as_a_jpeg(): void
    {
        $client = self::createAdminClient();

        self::upload($client, self::FIXTURES . '/cookie.jpg', 'cookie.png', self::getCsrfToken($client), 'image/png');

        self::assertResponseIsSuccessful();
        self::assertSame(['url' => '/media/terms/' . self::JPEG], self::getJson($client));
        self::assertFileEquals(self::FIXTURES . '/cookie.jpg', self::STORAGE_DIRECTORY . '/' . self::JPEG);
    }

    #[Test]
    public function the_same_image_uploaded_twice_gets_the_same_url_and_is_stored_once(): void
    {
        $client = self::createAdminClient();
        $token = self::getCsrfToken($client);
        $stored = self::getStoredImages();

        self::upload($client, self::FIXTURES . '/cookie.png', 'cookie.png', $token);
        $first = self::getJson($client);
        self::upload($client, self::FIXTURES . '/cookie.png', 'screenshot.png', $token);

        self::assertResponseIsSuccessful();
        self::assertSame($first, self::getJson($client));
        self::assertSame([self::PNG], array_values(array_diff(self::getStoredImages(), $stored)));
    }

    /**
     * Each error is translated and names the file, as the editor shows it below the textarea
     */
    #[Test]
    #[DataProvider('provideFilesThatAreNotAllowedImages')]
    public function a_file_that_is_not_an_allowed_image_gets_an_error_that_names_it_and_nothing_is_stored(string $fixture, string $error): void
    {
        $client = self::createAdminClient();
        $token = self::getCsrfToken($client);
        $stored = self::getStoredImages();

        self::upload($client, self::FIXTURES . '/' . $fixture, $fixture, $token, 'image/png');

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => $error], self::getJson($client));
        self::assertSame($stored, self::getStoredImages());
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function provideFilesThatAreNotAllowedImages(): \Generator
    {
        yield 'pdf' => ['cookie.pdf', "cookie.pdf isn't a PNG, JPEG, GIF or WebP image."];
        yield 'svg' => ['cookie.svg', "cookie.svg isn't a PNG, JPEG, GIF or WebP image."];
    }

    /**
     * The test client, as PHP, stops an upload larger than PHP's upload_max_filesize or post_max_size before the
     * endpoint sees it. Those limits are 2M and 8M by default, so the 5 MB cap is tested in the unit and browser suites
     */
    #[Test]
    public function an_image_larger_than_php_accepts_gets_an_error_that_names_it_and_nothing_is_stored(): void
    {
        $limit = UploadedFile::getMaxFilesize();
        if ($limit > 16 * 1024 * 1024) {
            self::markTestSkipped(sprintf('PHP accepts uploads of %d bytes, more than this test makes', $limit));
        }

        $client = self::createAdminClient();
        $token = self::getCsrfToken($client);
        $stored = self::getStoredImages();

        self::upload($client, $this->createPaddedPng((int) $limit + 1), 'large.png', $token);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'large.png is larger than the server accepts.'], self::getJson($client));
        self::assertSame($stored, self::getStoredImages());
    }

    #[Test]
    public function the_error_is_in_the_admins_language(): void
    {
        $client = self::createAdminClient('da');

        self::upload($client, self::FIXTURES . '/cookie.pdf', 'cookie.pdf', self::getCsrfToken($client));

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'cookie.pdf er ikke et PNG-, JPEG-, GIF- eller WebP-billede.'], self::getJson($client));
    }

    #[Test]
    public function an_anonymous_request_is_sent_to_the_login_page_and_nothing_is_stored(): void
    {
        $client = self::createClient();
        $stored = self::getStoredImages();

        self::upload($client, self::FIXTURES . '/cookie.png', 'cookie.png', 'token');

        self::assertResponseRedirects('http://localhost/admin/login');
        self::assertSame($stored, self::getStoredImages());
    }

    #[Test]
    #[DataProvider('provideInvalidCsrfTokens')]
    public function a_request_without_a_valid_csrf_token_is_refused_and_nothing_is_stored(?string $token): void
    {
        $client = self::createAdminClient();
        // The admin has a valid token in the session, which the request doesn't send
        self::getCsrfToken($client);
        $stored = self::getStoredImages();

        self::upload($client, self::FIXTURES . '/cookie.png', 'cookie.png', $token);

        self::assertResponseStatusCodeSame(403);
        self::assertSame($stored, self::getStoredImages());
    }

    /**
     * @return \Generator<string, array{string|null}>
     */
    public static function provideInvalidCsrfTokens(): \Generator
    {
        yield 'no token' => [null];
        yield 'invalid token' => ['not-the-token'];
    }

    #[Test]
    public function a_request_without_an_image_is_a_bad_request(): void
    {
        $client = self::createAdminClient();

        $client->request('POST', '/admin/terms/uploaded-images', ['_csrf_token' => self::getCsrfToken($client)]);

        self::assertResponseStatusCodeSame(400);
    }

    private static function createAdminClient(string $localeCode = 'en_US'): KernelBrowser
    {
        $client = self::createClient();

        /** @var UserRepositoryInterface<AdminUserInterface> $repository */
        $repository = self::getContainer()->get('sylius.repository.admin_user');
        $admin = $repository->findOneBy(['username' => 'sylius']);
        self::assertInstanceOf(AdminUserInterface::class, $admin);
        // Each request reloads the admin from the database
        $admin->setLocaleCode($localeCode);
        $repository->add($admin);

        $client->loginUser($admin, 'admin');

        return $client;
    }

    /**
     * The CSRF token the create page gives the Content editor
     */
    private static function getCsrfToken(KernelBrowser $client): string
    {
        $crawler = $client->request('GET', '/admin/terms/new');
        self::assertResponseIsSuccessful();

        $token = $crawler->filter('[data-test-content-upload="en_US"]')->attr('data-csrf-token');
        self::assertIsString($token);

        return $token;
    }

    private static function upload(KernelBrowser $client, string $path, string $clientName, ?string $token, ?string $clientMimeType = null): void
    {
        $client->request(
            'POST',
            '/admin/terms/uploaded-images',
            null === $token ? [] : ['_csrf_token' => $token],
            ['image' => new UploadedFile($path, $clientName, $clientMimeType, \UPLOAD_ERR_OK, true)],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function getJson(KernelBrowser $client): array
    {
        /** @var array<string, mixed> $json */
        $json = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $json;
    }

    /**
     * The PNG fixture followed by zeros up to the size. Its type is still detected as PNG
     */
    private function createPaddedPng(int $size): string
    {
        $path = tempnam(sys_get_temp_dir(), 'setono_sylius_terms_upload_');
        self::assertIsString($path);
        file_put_contents($path, str_pad((string) file_get_contents(self::FIXTURES . '/cookie.png'), $size, "\0"));
        $this->temporaryFiles[] = $path;

        return $path;
    }
}
