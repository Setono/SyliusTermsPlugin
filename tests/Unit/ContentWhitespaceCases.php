<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit;

/**
 * Content with the whitespace Markdown ignores, shared by the tests of both setContent()s and of the check for a new
 * Terms version, which apply the same rule
 */
final class ContentWhitespaceCases
{
    /**
     * @return iterable<string, array{string, string}> the Content as given, and as stored
     */
    public static function provideContentWithWhitespaceMarkdownIgnores(): iterable
    {
        yield 'leading line breaks' => ["\n\n## Cookies\n\nWe use cookies.", "## Cookies\n\nWe use cookies."];
        yield 'leading lines of spaces and tabs' => [" \t\n  \n## Cookies", '## Cookies'];
        yield 'leading CRLF and CR line breaks' => ["\r\n \r## Cookies", '## Cookies'];
        yield 'leading blank lines before an indented code block' => ["\n  \n    composer require\n\nWe use cookies.", "    composer require\n\nWe use cookies."];
        yield 'trailing line breaks' => ["We use cookies.\n\n", 'We use cookies.'];
        yield 'trailing spaces and tabs' => ["We use cookies. \t", 'We use cookies.'];
        yield 'trailing CRLF line breaks' => ["We use cookies.\r\n\r\n", 'We use cookies.'];
        yield 'trailing lines of spaces and tabs' => ["We use cookies.  \n \t\n ", 'We use cookies.'];
        yield 'both ends' => ["\n\n## Cookies\n\nWe use cookies.\n", "## Cookies\n\nWe use cookies."];
        yield 'whitespace between the first and last non-blank characters' => ["\t## Cookies  \n \n\n\tWe use  cookies.  \nNo tracking.", "\t## Cookies  \n \n\n\tWe use  cookies.  \nNo tracking."];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideContentThatIsOnlyWhitespace(): iterable
    {
        yield 'empty' => [''];
        yield 'spaces' => ['   '];
        yield 'a line break' => ["\n"];
        yield 'spaces, tabs and line breaks' => [" \t\r\n\n  \r \t"];
    }
}
