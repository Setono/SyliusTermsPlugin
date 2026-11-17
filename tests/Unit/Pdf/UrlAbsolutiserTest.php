<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit\Pdf;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusTermsPlugin\Pdf\UrlAbsolutiser;
use Sylius\Component\Channel\Model\ChannelInterface;

final class UrlAbsolutiserTest extends TestCase
{
    use ProphecyTrait;

    #[Test]
    public function it_makes_relative_link_and_image_urls_absolute_against_the_channel_hostname(): void
    {
        $html = (new UrlAbsolutiser())->absolutise(
            self::document('<p><a href="/en_US/contact">Contact us</a> <img src="/media/image/logo.png" alt="Logo"></p>'),
            $this->channel('shop.example.com'),
        );

        self::assertStringContainsString('<a href="https://shop.example.com/en_US/contact">Contact us</a>', $html);
        self::assertStringContainsString('<img src="https://shop.example.com/media/image/logo.png" alt="Logo">', $html);
    }

    #[Test]
    public function it_uses_http_when_sylius_uses_unsecured_urls(): void
    {
        $html = (new UrlAbsolutiser(true))->absolutise(
            self::document('<a href="/en_US/contact">Contact us</a>'),
            $this->channel('shop.example.com'),
        );

        self::assertStringContainsString('<a href="http://shop.example.com/en_US/contact">', $html);
    }

    #[Test]
    #[DataProvider('provideUrls')]
    public function it_resolves_each_kind_of_url(string $url, string $expected): void
    {
        $html = (new UrlAbsolutiser())->absolutise(
            self::document(sprintf('<a href="%1$s">Link</a><img src="%1$s" alt="Image">', $url)),
            $this->channel('shop.example.com'),
        );

        self::assertStringContainsString(sprintf('<a href="%s">Link</a>', $expected), $html);
        self::assertStringContainsString(sprintf('<img src="%s" alt="Image">', $expected), $html);
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function provideUrls(): \Generator
    {
        yield 'path from the root' => ['/terms/privacy-policy', 'https://shop.example.com/terms/privacy-policy'];
        yield 'relative path' => ['media/logo.png', 'https://shop.example.com/media/logo.png'];
        yield 'relative path from the current directory' => ['./media/logo.png', 'https://shop.example.com/media/logo.png'];
        yield 'relative path from the parent directory' => ['../media/logo.png', 'https://shop.example.com/media/logo.png'];
        yield 'query only' => ['?page=2', 'https://shop.example.com/?page=2'];
        yield 'network-path reference' => ['//cdn.example.com/logo.png', 'https://cdn.example.com/logo.png'];
        yield 'surrounding whitespace' => [' /terms ', 'https://shop.example.com/terms'];
        yield 'absolute https URL' => ['https://cdn.example.com/logo.png', 'https://cdn.example.com/logo.png'];
        yield 'absolute http URL' => ['http://cdn.example.com/logo.png', 'http://cdn.example.com/logo.png'];
        yield 'upper case scheme' => ['HTTPS://cdn.example.com/logo.png', 'HTTPS://cdn.example.com/logo.png'];
        yield 'mailto' => ['mailto:info@example.com', 'mailto:info@example.com'];
        yield 'tel' => ['tel:+4512345678', 'tel:+4512345678'];
        yield 'data URI' => ['data:image/png;base64,iVBORw0KGgo=', 'data:image/png;base64,iVBORw0KGgo='];
        yield 'link within the document' => ['#payment', '#payment'];
    }

    #[Test]
    public function it_leaves_elements_without_the_url_attribute_alone(): void
    {
        $html = (new UrlAbsolutiser())->absolutise(
            self::document('<a id="top">Top</a><img alt="No source">'),
            $this->channel('shop.example.com'),
        );

        self::assertStringContainsString('<a id="top">Top</a><img alt="No source">', $html);
    }

    #[Test]
    #[DataProvider('provideNoHostname')]
    public function it_changes_nothing_without_a_hostname(?string $hostname): void
    {
        $document = self::document('<a href="/en_US/contact">Contact us</a> <img src="/media/image/logo.png" alt="Logo">');

        self::assertSame($document, (new UrlAbsolutiser())->absolutise($document, $this->channel($hostname)));
    }

    /**
     * @return \Generator<string, array{string|null}>
     */
    public static function provideNoHostname(): \Generator
    {
        yield 'null' => [null];
        yield 'empty' => [''];
    }

    #[Test]
    public function it_keeps_the_document_and_its_non_ascii_text(): void
    {
        $html = (new UrlAbsolutiser())->absolutise(
            self::document('<h2 id="betaling">Betaling</h2><p>Æblegrød på <a href="/da_DK/kontakt">kontakt</a></p>'),
            $this->channel('shop.example.com'),
        );

        self::assertStringContainsString('<html lang="da"><head><meta charset="UTF-8"><title>Handelsbetingelser</title></head>', $html);
        self::assertStringContainsString('<h2 id="betaling">Betaling</h2><p>Æblegrød på <a href="https://shop.example.com/da_DK/kontakt">kontakt</a></p>', $html);
    }

    private function channel(?string $hostname): ChannelInterface
    {
        $channel = $this->prophesize(ChannelInterface::class);
        $channel->getHostname()->willReturn($hostname);

        return $channel->reveal();
    }

    private static function document(string $body): string
    {
        return sprintf('<!DOCTYPE html><html lang="da"><head><meta charset="UTF-8"><title>Handelsbetingelser</title></head><body>%s</body></html>', $body);
    }
}
