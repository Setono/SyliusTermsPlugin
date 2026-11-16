<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit\Sanitizer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Setono\SyliusTermsPlugin\Sanitizer\HeadingIdAttributeSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

final class HeadingIdAttributeSanitizerTest extends TestCase
{
    #[Test]
    public function it_supports_the_id_attribute_on_every_element(): void
    {
        $sanitizer = new HeadingIdAttributeSanitizer();

        self::assertNull($sanitizer->getSupportedElements());
        self::assertSame(['id'], $sanitizer->getSupportedAttributes());
    }

    #[Test]
    #[DataProvider('provideHeadings')]
    public function it_keeps_the_id_on_headings(string $element): void
    {
        self::assertSame('content-payment', (new HeadingIdAttributeSanitizer())->sanitizeAttribute($element, 'id', 'content-payment', new HtmlSanitizerConfig()));
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function provideHeadings(): \Generator
    {
        foreach (['h1', 'h2', 'h3', 'h4', 'h5', 'h6'] as $heading) {
            yield $heading => [$heading];
        }
    }

    #[Test]
    #[DataProvider('provideOtherElements')]
    public function it_drops_the_id_from_other_elements(string $element): void
    {
        self::assertNull((new HeadingIdAttributeSanitizer())->sanitizeAttribute($element, 'id', 'content-payment', new HtmlSanitizerConfig()));
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function provideOtherElements(): \Generator
    {
        foreach (['a', 'p', 'div', 'img', 'header'] as $element) {
            yield $element => [$element];
        }
    }
}
