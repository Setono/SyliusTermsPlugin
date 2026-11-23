<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Setono\SyliusTermsPlugin\Model\TermsVersionTranslation;
use Setono\SyliusTermsPlugin\Tests\Unit\ContentWhitespaceCases;

final class TermsVersionTranslationTest extends TestCase
{
    #[Test]
    #[DataProvider('provideContentWithLineBreaks')]
    public function its_content_has_lf_line_breaks_whatever_it_is_given(string $content): void
    {
        $translation = new TermsVersionTranslation();
        $translation->setContent($content);

        self::assertSame("## Cookies\n\nWe use cookies.\nNo tracking.", $translation->getContent());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideContentWithLineBreaks(): iterable
    {
        yield 'LF' => ["## Cookies\n\nWe use cookies.\nNo tracking."];
        yield 'CRLF' => ["## Cookies\r\n\r\nWe use cookies.\r\nNo tracking."];
        yield 'CR' => ["## Cookies\r\rWe use cookies.\rNo tracking."];
        yield 'mixed' => ["## Cookies\r\n\rWe use cookies.\nNo tracking."];
    }

    #[Test]
    #[DataProviderExternal(ContentWhitespaceCases::class, 'provideContentWithWhitespaceMarkdownIgnores')]
    public function its_content_is_stored_without_the_whitespace_markdown_ignores(string $content, string $stored): void
    {
        $translation = new TermsVersionTranslation();
        $translation->setContent($content);

        self::assertSame($stored, $translation->getContent());
    }

    #[Test]
    #[DataProviderExternal(ContentWhitespaceCases::class, 'provideContentThatIsOnlyWhitespace')]
    public function content_that_is_only_whitespace_is_no_content(string $content): void
    {
        $translation = new TermsVersionTranslation();
        $translation->setContent($content);

        self::assertNull($translation->getContent());
    }

    #[Test]
    public function its_content_can_be_removed(): void
    {
        $translation = new TermsVersionTranslation();
        $translation->setContent('We use cookies.');
        $translation->setContent(null);

        self::assertNull($translation->getContent());
    }
}
