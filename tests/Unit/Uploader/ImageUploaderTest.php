<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit\Uploader;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusTermsPlugin\Uploader\ImageUploader;
use Setono\SyliusTermsPlugin\Uploader\RejectedImageException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ImageUploaderTest extends TestCase
{
    use ProphecyTrait;

    private const FIXTURES = __DIR__ . '/../../Fixtures/images';

    /** @var ObjectProphecy<FilesystemOperator> */
    private ObjectProphecy $storage;

    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        $this->storage = $this->prophesize(FilesystemOperator::class);
        $this->storage->publicUrl(Argument::type('string'))->will(static function (array $arguments): string {
            [$name] = $arguments;
            self::assertIsString($name);

            return '/media/terms/' . $name;
        });
    }

    protected function tearDown(): void
    {
        array_map(unlink(...), $this->temporaryFiles);
    }

    #[Test]
    public function it_stores_a_png_under_the_sha256_of_its_bytes_and_returns_its_public_url(): void
    {
        $name = 'a2cb41b89e07d4fe99464fdd159b8bdd0e9360ca50c0386151fba6efde07593b.png';
        $this->storage->fileExists($name)->willReturn(false);
        $this->storage->write($name, self::bytesOf('cookie.png'))->shouldBeCalledOnce();

        $url = $this->createUploader()->upload(self::upload('cookie.png'));

        self::assertSame('/media/terms/' . $name, $url);
    }

    #[Test]
    public function it_does_not_write_an_image_that_is_stored_already_and_returns_the_same_url(): void
    {
        $name = 'a2cb41b89e07d4fe99464fdd159b8bdd0e9360ca50c0386151fba6efde07593b.png';
        $this->storage->fileExists($name)->willReturn(true);
        $this->storage->write(Argument::cetera())->shouldNotBeCalled();

        // Under another name, with the same bytes
        $url = $this->createUploader()->upload(self::upload('cookie.png', 'screenshot.png'));

        self::assertSame('/media/terms/' . $name, $url);
    }

    /**
     * The extension comes from the type the bytes show, not from the name or the MIME type the client sent
     */
    #[Test]
    #[DataProvider('provideImages')]
    public function it_names_the_image_with_the_extension_of_its_detected_type(string $fixture, string $name): void
    {
        $this->storage->fileExists($name)->willReturn(false);
        $this->storage->write($name, self::bytesOf($fixture))->shouldBeCalledOnce();

        $url = $this->createUploader()->upload(self::upload($fixture, 'photo.png', 'image/png'));

        self::assertSame('/media/terms/' . $name, $url);
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function provideImages(): \Generator
    {
        yield 'jpeg' => ['cookie.jpg', '9477a0aeadd5a51a8e300f7c30b779d9632aec4e48b5f3c31ca3af3a05ad95d2.jpg'];
        yield 'gif' => ['cookie.gif', 'f215c42dc42f63e93a00df17a26714d92381ed1fe4bbc2f5a351f8f1ee3059d7.gif'];
        yield 'webp' => ['cookie.webp', '521b7610975d6dd3eb908d6b3277f2cb9b39a1cb8132d8c187156ea337017c99.webp'];
    }

    /**
     * Judged by the bytes, so a PDF named and sent as a PNG is rejected too. SVG isn't allowed: it can hold scripts
     */
    #[Test]
    #[DataProvider('provideFilesThatAreNotAllowedImages')]
    public function it_rejects_a_file_that_is_not_a_png_jpeg_gif_or_webp_image_and_stores_nothing(string $fixture, string $clientName): void
    {
        $this->storage->write(Argument::cetera())->shouldNotBeCalled();

        $exception = $this->catchRejection(self::upload($fixture, $clientName, 'image/png'));

        self::assertSame(sprintf('setono_sylius_terms.ui.uploaded_image.not_an_image {"%%name%%":"%s"}', $clientName), $this->translate($exception));
        self::assertSame(sprintf('"%s" is not a PNG, JPEG, GIF or WebP image', $clientName), $exception->getMessage());
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function provideFilesThatAreNotAllowedImages(): \Generator
    {
        yield 'svg' => ['cookie.svg', 'cookie.svg'];
        yield 'pdf' => ['cookie.pdf', 'cookie.pdf'];
        yield 'pdf named as a png' => ['cookie.pdf', 'cookie.png'];
    }

    #[Test]
    public function it_rejects_an_empty_file(): void
    {
        $this->storage->write(Argument::cetera())->shouldNotBeCalled();
        $path = $this->createTemporaryFile('');

        $exception = $this->catchRejection(new UploadedFile($path, 'empty.png', 'image/png', \UPLOAD_ERR_OK, true));

        self::assertSame('setono_sylius_terms.ui.uploaded_image.not_an_image {"%name%":"empty.png"}', $this->translate($exception));
    }

    /**
     * 5 MB is the cap the Terms PDF has when it downloads images, 5 × 1024 × 1024 bytes
     */
    #[Test]
    public function it_stores_an_image_of_5_mb(): void
    {
        $path = $this->createPaddedPng(5 * 1024 * 1024);
        $this->storage->fileExists(Argument::type('string'))->willReturn(false);
        $this->storage->write(Argument::containingString('.png'), Argument::that(static fn (mixed $bytes): bool => is_string($bytes) && 5 * 1024 * 1024 === strlen($bytes)))->shouldBeCalledOnce();

        $this->createUploader()->upload(new UploadedFile($path, 'large.png', 'image/png', \UPLOAD_ERR_OK, true));
    }

    #[Test]
    public function it_rejects_an_image_larger_than_5_mb_and_stores_nothing(): void
    {
        $path = $this->createPaddedPng(5 * 1024 * 1024 + 1);
        $this->storage->write(Argument::cetera())->shouldNotBeCalled();

        $exception = $this->catchRejection(new UploadedFile($path, 'large.png', 'image/png', \UPLOAD_ERR_OK, true));

        self::assertSame('setono_sylius_terms.ui.uploaded_image.too_large {"%name%":"large.png"}', $this->translate($exception));
        self::assertSame('"large.png" is larger than 5 MB', $exception->getMessage());
    }

    /**
     * PHP stops an upload larger than its upload_max_filesize, or than the form's MAX_FILE_SIZE, before the uploader
     * sees the file. That limit may be below 5 MB, so the error doesn't name a size
     */
    #[Test]
    #[DataProvider('provideUploadSizeErrors')]
    public function it_rejects_an_upload_php_stopped_for_its_size_as_larger_than_the_server_accepts(int $error): void
    {
        $this->storage->write(Argument::cetera())->shouldNotBeCalled();

        $exception = $this->catchRejection(new UploadedFile('', 'large.png', 'image/png', $error, true));

        self::assertSame('setono_sylius_terms.ui.uploaded_image.too_large_for_server {"%name%":"large.png"}', $this->translate($exception));
        self::assertSame('"large.png" is larger than the server accepts', $exception->getMessage());
    }

    /**
     * @return \Generator<string, array{int}>
     */
    public static function provideUploadSizeErrors(): \Generator
    {
        yield 'upload_max_filesize' => [\UPLOAD_ERR_INI_SIZE];
        yield 'MAX_FILE_SIZE' => [\UPLOAD_ERR_FORM_SIZE];
    }

    #[Test]
    #[DataProvider('provideUploadErrors')]
    public function it_rejects_a_failed_upload(int $error): void
    {
        $this->storage->write(Argument::cetera())->shouldNotBeCalled();

        $exception = $this->catchRejection(new UploadedFile('', 'cookie.png', 'image/png', $error, true));

        self::assertSame('setono_sylius_terms.ui.uploaded_image.upload_failed {"%name%":"cookie.png"}', $this->translate($exception));
        self::assertSame('"cookie.png" could not be uploaded', $exception->getMessage());
    }

    /**
     * @return \Generator<string, array{int}>
     */
    public static function provideUploadErrors(): \Generator
    {
        yield 'partial' => [\UPLOAD_ERR_PARTIAL];
        yield 'no file' => [\UPLOAD_ERR_NO_FILE];
        yield 'no temporary directory' => [\UPLOAD_ERR_NO_TMP_DIR];
        yield 'cannot write' => [\UPLOAD_ERR_CANT_WRITE];
        yield 'stopped by an extension' => [\UPLOAD_ERR_EXTENSION];
    }

    private function createUploader(): ImageUploader
    {
        return new ImageUploader($this->storage->reveal(), new MimeTypes());
    }

    private function catchRejection(UploadedFile $file): RejectedImageException
    {
        try {
            $this->createUploader()->upload($file);
        } catch (RejectedImageException $exception) {
            return $exception;
        }

        self::fail(sprintf('The upload of "%s" was not rejected', $file->getClientOriginalName()));
    }

    /**
     * Translates the exception with a translator that returns the key and the parameters it was given
     */
    private function translate(RejectedImageException $exception): string
    {
        $translator = $this->prophesize(TranslatorInterface::class);
        $translator->trans(Argument::type('string'), Argument::type('array'), 'messages', null)->will(
            static function (array $arguments): string {
                [$id, $parameters] = $arguments;
                self::assertIsString($id);

                return sprintf('%s %s', $id, json_encode($parameters, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES));
            },
        );

        return $exception->trans($translator->reveal());
    }

    /**
     * The PNG fixture followed by zeros up to the size. Its type is still detected as PNG
     */
    private function createPaddedPng(int $size): string
    {
        return $this->createTemporaryFile(str_pad(self::bytesOf('cookie.png'), $size, "\0"));
    }

    /**
     * A file holding the bytes, deleted after the test
     */
    private function createTemporaryFile(string $bytes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'setono_sylius_terms_upload_');
        self::assertIsString($path);
        file_put_contents($path, $bytes);
        $this->temporaryFiles[] = $path;

        return $path;
    }

    /**
     * An upload of the fixture, under the client's name and MIME type
     */
    private static function upload(string $fixture, ?string $clientName = null, ?string $clientMimeType = null): UploadedFile
    {
        return new UploadedFile(self::FIXTURES . '/' . $fixture, $clientName ?? $fixture, $clientMimeType, \UPLOAD_ERR_OK, true);
    }

    private static function bytesOf(string $fixture): string
    {
        $bytes = file_get_contents(self::FIXTURES . '/' . $fixture);
        self::assertIsString($bytes);

        return $bytes;
    }
}
