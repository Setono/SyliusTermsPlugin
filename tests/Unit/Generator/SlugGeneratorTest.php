<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit\Generator;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Setono\SyliusTermsPlugin\Generator\SlugGenerator;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class SlugGeneratorTest extends TestCase
{
    #[Test]
    #[DataProvider('provideNames')]
    public function it_generates_a_lowercase_slug_transliterated_for_the_locale(string $name, string $localeCode, string $slug): void
    {
        self::assertSame($slug, (new SlugGenerator(new AsciiSlugger()))->generate($name, $localeCode));
    }

    /**
     * @return \Generator<string, array{string, string, string}>
     */
    public static function provideNames(): \Generator
    {
        yield 'english' => ['Privacy Policy', 'en_US', 'privacy-policy'];
        yield 'symbols in english' => ['Terms & Conditions', 'en_US', 'terms-and-conditions'];
        yield 'german' => ['Datenschutzerklärung', 'de_DE', 'datenschutzerklaerung'];
        yield 'danish' => ['Handelsbetingelser for Ærø', 'da_DK', 'handelsbetingelser-for-aero'];
    }
}
