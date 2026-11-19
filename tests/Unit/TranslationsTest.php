<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * The files in translations/. English is the source, and every domain is translated into each target locale with the
 * same keys and placeholders. The translator falls back to English, so in the shop and the admin a key missing from a
 * locale's file shows in English instead of failing
 */
final class TranslationsTest extends TestCase
{
    private const DIRECTORY = __DIR__ . '/../../translations';

    private const DOMAINS = ['messages', 'validators'];

    /**
     * Nordic, large EU and other common Sylius locales
     */
    private const LOCALES = ['da', 'sv', 'no', 'fi', 'de', 'fr', 'es', 'it', 'nl', 'pl', 'pt', 'cs', 'hu', 'ro', 'uk'];

    #[Test]
    public function each_domain_has_a_file_for_english_and_for_each_target_locale(): void
    {
        $expected = [];
        foreach (self::DOMAINS as $domain) {
            foreach (['en', ...self::LOCALES] as $locale) {
                $expected[] = sprintf('%s.%s.yaml', $domain, $locale);
            }
        }

        $files = glob(self::DIRECTORY . '/*');
        self::assertIsArray($files);

        self::assertEqualsCanonicalizing($expected, array_map(basename(...), $files));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideDomainsAndLocales(): iterable
    {
        foreach (self::DOMAINS as $domain) {
            foreach (self::LOCALES as $locale) {
                yield sprintf('%s.%s', $domain, $locale) => [$domain, $locale];
            }
        }
    }

    #[Test]
    #[DataProvider('provideDomainsAndLocales')]
    public function it_translates_each_english_message_and_keeps_its_placeholders(string $domain, string $locale): void
    {
        $english = self::load($domain, 'en');
        $translated = self::load($domain, $locale);

        self::assertSame(array_keys($english), array_keys($translated), 'The file has the English keys, in the same order');

        foreach ($english as $key => $message) {
            self::assertNotSame('', trim($translated[$key]), $key);
            self::assertSame(self::getPlaceholders($message), self::getPlaceholders($translated[$key]), sprintf('%s keeps the placeholders', $key));
        }
    }

    /**
     * @return array<string, string> the messages by their full key
     */
    private static function load(string $domain, string $locale): array
    {
        $messages = Yaml::parseFile(sprintf('%s/%s.%s.yaml', self::DIRECTORY, $domain, $locale));
        self::assertIsArray($messages);

        return self::flatten($messages);
    }

    /**
     * @param array<array-key, mixed> $messages
     *
     * @return array<string, string>
     */
    private static function flatten(array $messages, string $prefix = ''): array
    {
        $flattened = [];
        foreach ($messages as $key => $message) {
            $key = $prefix . $key;

            if (is_array($message)) {
                $flattened += self::flatten($message, $key . '.');

                continue;
            }

            self::assertIsString($message, $key);
            $flattened[$key] = $message;
        }

        return $flattened;
    }

    /**
     * The message's %parameters%, {{ parameters }} and Link placeholders, sorted, since a translation may reorder them
     *
     * @return list<string>
     */
    private static function getPlaceholders(string $message): array
    {
        preg_match_all('/%\w+%|\{\{ ?\w+ ?\}\}|\[link:/', $message, $matches);
        $placeholders = $matches[0];
        sort($placeholders);

        return $placeholders;
    }
}
