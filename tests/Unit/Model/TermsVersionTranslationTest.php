<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Setono\SyliusTermsPlugin\Model\TermsVersionTranslation;

final class TermsVersionTranslationTest extends TestCase
{
    #[Test]
    #[DataProvider('provideContentWithLineBreaks')]
    public function its_content_has_lf_line_breaks_whatever_it_is_given(string $content): void
    {
        $translation = new TermsVersionTranslation();
        $translation->setContent($content);

        self::assertSame("## Cookies\n\nWe use cookies.\n", $translation->getContent());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideContentWithLineBreaks(): iterable
    {
        yield 'LF' => ["## Cookies\n\nWe use cookies.\n"];
        yield 'CRLF' => ["## Cookies\r\n\r\nWe use cookies.\r\n"];
        yield 'CR' => ["## Cookies\r\rWe use cookies.\r"];
        yield 'mixed' => ["## Cookies\r\n\rWe use cookies.\n"];
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
