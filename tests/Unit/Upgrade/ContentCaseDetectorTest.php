<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit\Upgrade;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Setono\SyliusTermsPlugin\Converter\ContentConverter;
use Setono\SyliusTermsPlugin\Upgrade\ContentCase;
use Setono\SyliusTermsPlugin\Upgrade\ContentCaseDetector;

final class ContentCaseDetectorTest extends TestCase
{
    private ContentCaseDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new ContentCaseDetector(new ContentConverter());
    }

    #[Test]
    #[DataProvider('provideContentWithOneCase')]
    public function it_flags_each_case(string $content, ContentCase $case): void
    {
        self::assertSame([$case], $this->detector->detect($content));
    }

    /**
     * @return \Generator<string, array{string, ContentCase}>
     */
    public static function provideContentWithOneCase(): \Generator
    {
        yield 'an HTML line indented by four spaces after a blank line' => ["<p>Intro</p>\n\n    <p>Indented</p>", ContentCase::IndentedCode];
        yield 'a text line indented by four spaces after a blank line' => ["Intro\n\n    Indented", ContentCase::IndentedCode];
        yield 'a tab after a blank line' => ["Intro\n\n\tIndented", ContentCase::IndentedCode];
        yield 'the first line indented by four spaces' => ['    Indented', ContentCase::IndentedCode];
        yield 'Windows line endings' => ["Intro\r\n\r\n    Indented", ContentCase::IndentedCode];

        yield 'text starting with 1.' => ['1. The seller is Acme', ContentCase::ListOrHeading];
        yield 'text starting with -' => ["Intro\n- The seller is Acme", ContentCase::ListOrHeading];
        yield 'text starting with #' => ['# Terms', ContentCase::ListOrHeading];
        yield 'text starting with 2. after a blank line' => ["Intro\n\n2. The buyer", ContentCase::ListOrHeading];
        yield 'text underlined with =' => ["Terms\n=====", ContentCase::ListOrHeading];
        yield 'text after a blank line in an HTML block' => ["<div>\n\n- The seller is Acme\n\n</div>", ContentCase::ListOrHeading];

        yield 'a class attribute' => ['<p class="lead">Terms</p>', ContentCase::ClassOrStyle];
        yield 'a style attribute' => ['<p style="color: red">Terms</p>', ContentCase::ClassOrStyle];
        yield 'an upper case class attribute' => ['<P CLASS="lead">Terms</P>', ContentCase::ClassOrStyle];
        yield 'a style attribute on an inline element' => ['Pay <span style="color: red">now</span>.', ContentCase::ClassOrStyle];
        yield 'a class attribute on a later line of a tag' => ["<div\n  id=\"terms\"\n  class=\"terms\">Terms</div>", ContentCase::ClassOrStyle];
    }

    #[Test]
    public function it_lists_every_case_once_in_order(): void
    {
        $content = <<<'CONTENT'
            <p style="color: red">Terms</p>

            # Terms

                Indented

            - One
            - Two

            <p class="lead">More</p>
            CONTENT;

        self::assertSame(
            [ContentCase::IndentedCode, ContentCase::ListOrHeading, ContentCase::ClassOrStyle],
            $this->detector->detect($content),
        );
    }

    #[Test]
    #[DataProvider('provideCleanContent')]
    public function it_does_not_flag_clean_markdown_or_html(string $content): void
    {
        self::assertSame([], $this->detector->detect($content));
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function provideCleanContent(): \Generator
    {
        yield 'HTML' => [<<<'HTML'
            <h2 id="payment">Payment</h2>
            <p>Pay <strong>now</strong>, see <a href="https://example.com" title="Example">example.com</a>.</p>
            <ol>
                <li>One
                    <ol>
                        <li>One point one</li>
                    </ol>
                </li>
            </ol>
            HTML];
        yield 'Markdown paragraphs' => [<<<'MARKDOWN'
            A paragraph with **bold**, _emphasis_ and a [link](https://example.com).
            2. doesn't start a list inside a paragraph, and #hashtag isn't a heading.

            A second paragraph.
            MARKDOWN];
        yield 'plain text' => ['The seller is Acme, and class="lead" in text is no attribute.'];
        yield 'attributes whose names end in class or style' => ['<p data-class="lead" data-style="red">Terms</p>'];
        yield 'empty content' => [''];
    }
}
