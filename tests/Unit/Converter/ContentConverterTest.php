<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit\Converter;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Setono\SyliusTermsPlugin\Converter\ContentConverter;

/**
 * The content renderer's tests cover the converter together with the sanitiser. These cover what the converter does
 * on its own
 */
final class ContentConverterTest extends TestCase
{
    #[Test]
    public function it_drops_unsafe_markdown_link_and_image_urls_before_the_sanitiser_sees_them(): void
    {
        self::assertSame(
            "<p><a>Pay</a> <img src=\"\" alt=\"Logo\" /> <a href=\"https://example.com\">Shop</a></p>\n",
            (string) (new ContentConverter())->convert('[Pay](javascript:alert(1)) ![Logo](data:text/html;base64,PHA+) [Shop](https://example.com)'),
        );
    }
}
