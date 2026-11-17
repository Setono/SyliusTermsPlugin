<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit\Pdf;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Setono\SyliusTermsPlugin\Pdf\ImageInliner;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ImageInlinerTest extends TestCase
{
    private const PNG = "\x89PNG\r\n\x1a\nimage data";

    #[Test]
    #[DataProvider('provideImageUrls')]
    public function it_inlines_an_http_or_https_image_as_a_data_uri_with_the_responses_image_type(string $url): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(self::PNG, ['response_headers' => ['content-type' => 'image/png']]),
        ]);

        $inlinedHtml = (new ImageInliner($httpClient))->inline(self::document(sprintf('<p><img src="%s" alt="Logo"></p>', $url)));

        self::assertTrue($inlinedHtml->isComplete());
        self::assertSame([], $inlinedHtml->failedImages);
        self::assertStringContainsString(sprintf('<p><img src="data:image/png;base64,%s" alt="Logo"></p>', base64_encode(self::PNG)), $inlinedHtml->html);
        self::assertSame(1, $httpClient->getRequestsCount());
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function provideImageUrls(): \Generator
    {
        yield 'https' => ['https://shop.example.com/media/logo.png'];
        yield 'http' => ['http://shop.example.com/media/logo.png'];
        yield 'upper case scheme' => ['HTTPS://shop.example.com/media/logo.png'];
    }

    #[Test]
    public function it_keeps_only_the_media_type_of_the_content_type(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('<svg xmlns="http://www.w3.org/2000/svg"/>', ['response_headers' => ['content-type' => 'Image/SVG+XML; charset=utf-8']]),
        ]);

        $inlinedHtml = (new ImageInliner($httpClient))->inline(self::document('<img src="https://shop.example.com/logo.svg" alt="Logo">'));

        self::assertStringContainsString('<img src="data:image/svg+xml;base64,', $inlinedHtml->html);
    }

    #[Test]
    public function it_downloads_with_a_timeout(): void
    {
        $options = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $requestOptions) use (&$options): MockResponse {
            $options = $requestOptions;

            return new MockResponse(self::PNG, ['response_headers' => ['content-type' => 'image/png']]);
        });

        (new ImageInliner($httpClient))->inline(self::document('<img src="https://shop.example.com/logo.png" alt="Logo">'));

        self::assertSame(5.0, $options['timeout'] ?? null);
        self::assertSame(5.0, $options['max_duration'] ?? null);
    }

    #[Test]
    public function it_downloads_an_image_used_twice_once(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(self::PNG, ['response_headers' => ['content-type' => 'image/png']]),
            new MockResponse('second', ['response_headers' => ['content-type' => 'image/png']]),
        ]);

        $inlinedHtml = (new ImageInliner($httpClient))->inline(self::document(
            '<img src="https://shop.example.com/logo.png" alt="Logo"><img src="https://shop.example.com/logo.png" alt="Logo again">',
        ));

        self::assertSame(1, $httpClient->getRequestsCount());
        self::assertSame(2, substr_count($inlinedHtml->html, sprintf('src="data:image/png;base64,%s"', base64_encode(self::PNG))));
    }

    /**
     * The size cap is 10 bytes here, so every other body is smaller than that
     */
    #[Test]
    #[DataProvider('provideFailingResponses')]
    public function it_replaces_an_image_that_cannot_be_downloaded_with_its_alt_text_and_marks_the_html_incomplete(
        MockResponse $response,
        string $reason,
    ): void {
        $inlinedHtml = (new ImageInliner(new MockHttpClient([$response]), maxBytes: 10))->inline(self::document(
            '<p>Our address: <img src="https://shop.example.com/map.png" alt="Main Street 1 &amp; 2"></p>',
        ));

        self::assertFalse($inlinedHtml->isComplete());
        self::assertSame(['https://shop.example.com/map.png'], array_keys($inlinedHtml->failedImages));
        self::assertStringContainsString($reason, $inlinedHtml->failedImages['https://shop.example.com/map.png']);
        self::assertStringContainsString('<p>Our address: Main Street 1 &amp; 2</p>', $inlinedHtml->html);
        self::assertStringNotContainsString('<img', $inlinedHtml->html);
    }

    /**
     * @return \Generator<string, array{MockResponse, string}>
     */
    public static function provideFailingResponses(): \Generator
    {
        // An empty chunk is MockResponse's way to simulate a timeout
        yield 'timeout' => [new MockResponse([''], ['response_headers' => ['content-type' => 'image/png']]), 'Idle timeout reached'];
        yield 'error status' => [new MockResponse('Not found', ['http_code' => 404]), 'HTTP 404'];
        yield 'server error' => [new MockResponse('Oops', ['http_code' => 500]), 'HTTP 500'];
        yield 'not an image' => [new MockResponse('<p></p>', ['response_headers' => ['content-type' => 'text/html; charset=UTF-8']]), 'The response is not an image, but "text/html"'];
        yield 'no content type' => [new MockResponse('abc'), 'The response is not an image, but ""'];
        yield 'over the size cap by its content length' => [new MockResponse(str_repeat('x', 11), ['response_headers' => ['content-type' => 'image/png', 'content-length' => '11']]), 'The image is larger than 10 bytes'];
        yield 'over the size cap while downloading' => [new MockResponse(['xxxxxx', 'xxxxxx'], ['response_headers' => ['content-type' => 'image/png']]), 'The image is larger than 10 bytes'];
    }

    #[Test]
    public function an_image_of_exactly_the_size_cap_is_inlined(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(str_repeat('x', 10), ['response_headers' => ['content-type' => 'image/png', 'content-length' => '10']]),
        ]);

        $inlinedHtml = (new ImageInliner($httpClient, maxBytes: 10))->inline(self::document('<img src="https://shop.example.com/logo.png" alt="Logo">'));

        self::assertTrue($inlinedHtml->isComplete());
    }

    #[Test]
    public function a_malformed_url_is_replaced_with_its_alt_text(): void
    {
        $httpClient = new MockHttpClient();

        $inlinedHtml = (new ImageInliner($httpClient))->inline(self::document('<img src="https://shop.example.com:99999/logo.png" alt="Logo">'));

        self::assertSame(['https://shop.example.com:99999/logo.png' => 'Malformed URL "https://shop.example.com:99999/logo.png".'], $inlinedHtml->failedImages);
        self::assertStringContainsString('<body>Logo</body>', $inlinedHtml->html);
        self::assertSame(0, $httpClient->getRequestsCount());
    }

    #[Test]
    public function an_image_without_alt_text_that_cannot_be_downloaded_is_removed(): void
    {
        $inlinedHtml = (new ImageInliner(new MockHttpClient([new MockResponse('', ['http_code' => 404])])))->inline(self::document(
            '<p>Before<img src="https://shop.example.com/logo.png">after</p>',
        ));

        self::assertFalse($inlinedHtml->isComplete());
        self::assertStringContainsString('<p>Beforeafter</p>', $inlinedHtml->html);
    }

    #[Test]
    public function only_the_images_that_fail_are_replaced(): void
    {
        $httpClient = new MockHttpClient(static fn (string $method, string $url): MockResponse => str_ends_with($url, 'logo.png')
            ? new MockResponse(self::PNG, ['response_headers' => ['content-type' => 'image/png']])
            : new MockResponse('', ['http_code' => 404]));

        $inlinedHtml = (new ImageInliner($httpClient))->inline(self::document(
            '<img src="https://shop.example.com/logo.png" alt="Logo"><img src="https://shop.example.com/map.png" alt="Map">',
        ));

        self::assertSame(['https://shop.example.com/map.png'], array_keys($inlinedHtml->failedImages));
        self::assertStringContainsString(sprintf('<body><img src="data:image/png;base64,%s" alt="Logo">Map</body>', base64_encode(self::PNG)), $inlinedHtml->html);
    }

    #[Test]
    #[DataProvider('provideSourcesThatAreNotDownloaded')]
    public function it_does_not_download_images_with_other_schemes(string $src): void
    {
        $httpClient = new MockHttpClient();
        $document = self::document(sprintf('<img src="%s" alt="Logo">', $src));

        $inlinedHtml = (new ImageInliner($httpClient))->inline($document);

        self::assertSame(0, $httpClient->getRequestsCount());
        self::assertTrue($inlinedHtml->isComplete());
        self::assertSame($document, $inlinedHtml->html, 'The HTML is returned as it was');
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function provideSourcesThatAreNotDownloaded(): \Generator
    {
        yield 'relative' => ['/media/image/logo.png'];
        yield 'network-path reference' => ['//cdn.example.com/logo.png'];
        yield 'data' => ['data:image/png;base64,iVBORw0KGgo='];
        yield 'file' => ['file:///etc/passwd'];
        yield 'ftp' => ['ftp://example.com/logo.png'];
        yield 'empty' => [''];
    }

    #[Test]
    public function it_returns_html_without_images_as_it_was(): void
    {
        $document = self::document('<p>No images</p>');

        self::assertSame($document, (new ImageInliner(new MockHttpClient()))->inline($document)->html);
    }

    private static function document(string $body): string
    {
        return sprintf('<!DOCTYPE html><html lang="da"><head><meta charset="UTF-8"><title>Handelsbetingelser</title></head><body>%s</body></html>', $body);
    }
}
