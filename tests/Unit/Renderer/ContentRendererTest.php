<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit\Renderer;

use League\CommonMark\CommonMarkConverter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Setono\SyliusTermsPlugin\Converter\ContentConverter;
use Setono\SyliusTermsPlugin\DependencyInjection\SetonoSyliusTermsExtension;
use Setono\SyliusTermsPlugin\Renderer\ContentRenderer;
use Setono\SyliusTermsPlugin\Renderer\OutlineHeading;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;
use Webmozart\Assert\Assert;

final class ContentRendererTest extends TestCase
{
    #[Test]
    public function it_renders_headings_at_the_level_the_author_wrote_them(): void
    {
        self::assertRenders(
            <<<'HTML'
                <h1 id="content-one">One</h1>
                <h2 id="content-two">Two<a href="#content-two" title="Permalink">#</a></h2>
                <h3 id="content-three">Three</h3>
                <h6 id="content-six">Six</h6>

                HTML,
            "# One\n\n## Two\n\n### Three\n\n###### Six",
        );
    }

    #[Test]
    public function it_renders_emphasis(): void
    {
        self::assertRenders(
            "<p><em>Emphasis</em> and <strong>strong emphasis</strong></p>\n",
            '*Emphasis* and **strong emphasis**',
        );
    }

    #[Test]
    public function it_renders_nested_ordered_lists(): void
    {
        self::assertRenders(
            <<<'HTML'
                <ol>
                <li>Scope
                <ol>
                <li>These terms apply to every order</li>
                <li>They apply in every channel</li>
                </ol>
                </li>
                <li>Payment</li>
                </ol>

                HTML,
            <<<'MARKDOWN'
                1. Scope
                   1. These terms apply to every order
                   2. They apply in every channel
                2. Payment
                MARKDOWN,
        );
    }

    #[Test]
    public function it_renders_tables(): void
    {
        self::assertRenders(
            <<<'HTML'
                <table>
                <thead>
                <tr>
                <th>Method</th>
                <th align="right">Fee</th>
                </tr>
                </thead>
                <tbody>
                <tr>
                <td>Card</td>
                <td align="right">0</td>
                </tr>
                </tbody>
                </table>

                HTML,
            <<<'MARKDOWN'
                | Method | Fee |
                |--------|----:|
                | Card   | 0   |
                MARKDOWN,
        );
    }

    #[Test]
    public function it_renders_autolinks(): void
    {
        self::assertRenders(
            "<p>Visit <a href=\"http://www.example.com\">www.example.com</a> or <a href=\"https://example.com/help\">https://example.com/help</a>, or write to <a href=\"mailto:shop&#64;example.com\">shop&#64;example.com</a></p>\n",
            'Visit www.example.com or https://example.com/help, or write to shop@example.com',
        );
    }

    #[Test]
    public function it_passes_raw_html_through(): void
    {
        self::assertRenders(
            "<div>\n<p>Prices are <u>final</u> and include VAT<sup>1</sup>.</p>\n</div>\n",
            "<div>\n<p>Prices are <u>final</u> and include VAT<sup>1</sup>.</p>\n</div>",
        );
    }

    #[Test]
    #[DataProvider('provideUnsafeContent')]
    public function it_removes_unsafe_markup(string $expected, string $content): void
    {
        self::assertRenders($expected, $content);
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function provideUnsafeContent(): \Generator
    {
        yield 'script' => ["<p>Before</p>\n\n<p>After</p>\n", "Before\n\n<script>alert(1)</script>\n\nAfter"];
        yield 'event handler' => ["<p>Text</p>\n", '<p onclick="alert(1)">Text</p>'];
        yield 'event handler on an image' => ["<img src=\"https://example.com/logo.png\" />\n", '<img src="https://example.com/logo.png" onerror="alert(1)">'];
        yield 'javascript: link' => ["<p><a>Click</a></p>\n", '[Click](javascript:alert(1))'];
        yield 'javascript: link in raw HTML' => ["<p><a>Click</a></p>\n", '<a href="javascript:alert(1)">Click</a>'];
        yield 'data: link' => ["<p><a>Click</a></p>\n", '[Click](data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==)'];
        yield 'data: link in raw HTML' => ["<p><a>Click</a></p>\n", '<a href="data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==">Click</a>'];
        yield 'data: image source' => ["<p><img alt=\"Pixel\" /></p>\n", '![Pixel](data:image/png;base64,iVBORw0KGgo=)'];
        yield 'data: image source in raw HTML' => ["<img alt=\"Pixel\" />\n", '<img src="data:image/png;base64,iVBORw0KGgo=" alt="Pixel">'];
        yield 'class and style' => ["<p>Text</p>\n", '<p class="lead" style="color: red">Text</p>'];
    }

    #[Test]
    public function it_keeps_images_with_an_https_source_an_alt_and_a_title(): void
    {
        self::assertRenders(
            "<p><img src=\"https://example.com/logo.png\" alt=\"Logo\" title=\"Our logo\" /></p>\n",
            '![Logo](https://example.com/logo.png "Our logo")',
        );
    }

    #[Test]
    public function it_keeps_relative_links_and_images(): void
    {
        self::assertRenders(
            "<p><a href=\"/en_US/terms/privacy-policy\">Privacy policy</a> <a href=\"#content-payment\">Payment</a> <img src=\"/media/logo.png\" alt=\"Logo\" /></p>\n",
            '[Privacy policy](/en_US/terms/privacy-policy) [Payment](#content-payment) ![Logo](/media/logo.png)',
        );
    }

    #[Test]
    public function it_renders_content_longer_than_20000_characters_untruncated(): void
    {
        $content = str_repeat("The seller may change these terms at any time.\n\n", 1000) . 'The end.';
        self::assertGreaterThan(20_000, strlen($content));

        $html = self::createRenderer()->render($content)->html;

        self::assertSame(1001, substr_count($html, '<p>'));
        self::assertStringEndsWith("<p>The end.</p>\n", $html);
    }

    /**
     * 2.x stored Content as HTML typed into a textarea and printed it with |raw
     */
    #[Test]
    public function it_renders_a_2x_html_row_like_2x_did_minus_disallowed_markup(): void
    {
        self::assertRenders(
            <<<'HTML'
                <h2>1. General</h2>
                <p>These terms apply to every order placed at <a href="https://example.com" target="_blank">example.com</a>.</p>
                <h2>2. Payment</h2>
                <p>We accept:</p>
                <ul>
                    <li>Credit card</li>
                    <li>Bank transfer</li>
                </ul>
                <p>Prices include <strong>VAT</strong>.<br />Questions? Write to <a href="mailto:shop&#64;example.com">shop&#64;example.com</a>.</p>
                <p><a href="#">Print</a></p>


                HTML,
            <<<'HTML'
                <h2>1. General</h2>
                <p>These terms apply to every order placed at <a href="https://example.com" target="_blank">example.com</a>.</p>
                <h2>2. Payment</h2>
                <p>We accept:</p>
                <ul>
                    <li>Credit card</li>
                    <li>Bank transfer</li>
                </ul>
                <p class="note" style="color: red">Prices include <strong>VAT</strong>.<br>Questions? Write to <a href="mailto:shop@example.com">shop@example.com</a>.</p>
                <p><a href="#" onclick="window.print()">Print</a></p>
                <script>trackTermsView();</script>
                HTML,
        );
    }

    #[Test]
    public function it_renders_a_line_indented_by_four_spaces_after_a_blank_line_as_a_code_block(): void
    {
        self::assertRenders(
            "<p>Our terms:</p>\n<pre><code>&lt;p&gt;Every order is binding.&lt;/p&gt;\n</code></pre>\n",
            "<p>Our terms:</p>\n\n    <p>Every order is binding.</p>",
        );
    }

    #[Test]
    #[DataProvider('providePlainTextThatBecomesMarkdown')]
    public function it_renders_plain_text_that_starts_with_a_markdown_marker_as_markdown(string $expected, string $content): void
    {
        self::assertRenders($expected, $content);
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function providePlainTextThatBecomesMarkdown(): \Generator
    {
        yield '1.' => ["<ol>\n<li>Every order is binding.</li>\n</ol>\n", '1. Every order is binding.'];
        yield '-' => ["<ul>\n<li>Every order is binding.</li>\n</ul>\n", '- Every order is binding.'];
        yield '#' => ["<h1 id=\"content-every-order-is-binding\">Every order is binding.</h1>\n", '# Every order is binding.'];
    }

    #[Test]
    public function it_gives_markdown_headings_stable_ids_and_duplicate_headings_unique_ones(): void
    {
        $renderer = self::createRenderer();
        $content = "## Payment\n\n### Payment\n\n## Payment";

        $expected = <<<'HTML'
            <h2 id="content-payment">Payment<a href="#content-payment" title="Permalink">#</a></h2>
            <h3 id="content-payment-1">Payment</h3>
            <h2 id="content-payment-2">Payment<a href="#content-payment-2" title="Permalink">#</a></h2>

            HTML;

        self::assertSame($expected, $renderer->render($content)->html);
        self::assertSame($expected, $renderer->render($content)->html);
    }

    #[Test]
    public function it_outlines_the_section_headings_in_order_as_plain_text(): void
    {
        $content = <<<'MARKDOWN'
            # Terms and conditions

            ## Scope

            ##

            ### Who these terms apply to

            ## Payment *and* `delivery` <em>times</em>

            ## Privacy <br>

            Returns &amp;
            refunds
            ---------
            MARKDOWN;

        // The empty heading is left out, and the outline goes on after it
        self::assertEquals([
            new OutlineHeading('content-scope', 'Scope'),
            new OutlineHeading('content-payment-and-delivery-times', 'Payment and delivery times'),
            new OutlineHeading('content-privacy', 'Privacy'),
            new OutlineHeading('content-returns-refunds', 'Returns & refunds'),
        ], self::createRenderer()->render($content)->outline);
    }

    #[Test]
    public function it_gives_headings_written_as_raw_html_no_id_and_leaves_them_out_of_the_outline(): void
    {
        $rendered = self::createRenderer()->render("<h2>Scope</h2>\n\n## Payment");

        self::assertSame(
            "<h2>Scope</h2>\n<h2 id=\"content-payment\">Payment<a href=\"#content-payment\" title=\"Permalink\">#</a></h2>\n",
            $rendered->html,
        );
        self::assertEquals([new OutlineHeading('content-payment', 'Payment')], $rendered->outline);
    }

    #[Test]
    public function it_keeps_ids_on_headings_and_drops_them_from_other_elements(): void
    {
        self::assertRenders(
            "<h3 id=\"returns\">Returns</h3>\n<p>Send it back within <a href=\"#returns\">14 days</a>.</p>\n",
            "<h3 id=\"returns\">Returns</h3>\n<p id=\"intro\">Send it back within <a id=\"link\" href=\"#returns\">14 days</a>.</p>",
        );
    }

    #[Test]
    public function it_outlines_nothing_when_the_converter_gives_headings_no_ids(): void
    {
        $renderer = new ContentRenderer(new CommonMarkConverter(), self::createSanitizer());

        self::assertSame([], $renderer->render("## Scope\n\n## Payment")->outline);
    }

    private static function assertRenders(string $expectedHtml, string $content): void
    {
        self::assertSame($expectedHtml, self::createRenderer()->render($content)->html);
    }

    private static function createRenderer(): ContentRenderer
    {
        return new ContentRenderer(new ContentConverter(), self::createSanitizer());
    }

    /**
     * Builds the setono_sylius_terms.content sanitizer from the config the plugin prepends, the way FrameworkBundle does
     */
    private static function createSanitizer(): HtmlSanitizerInterface
    {
        $container = new ContainerBuilder();
        (new SetonoSyliusTermsExtension())->prepend($container);

        $framework = $container->getExtensionConfig('framework')[0];
        Assert::isArray($framework['html_sanitizer'] ?? null);
        Assert::isArray($framework['html_sanitizer']['sanitizers'] ?? null);
        $options = $framework['html_sanitizer']['sanitizers']['setono_sylius_terms.content'] ?? null;
        Assert::isArray($options);

        $config = new HtmlSanitizerConfig();

        /** @var mixed $value */
        foreach ($options as $option => $value) {
            $config = match ($option) {
                'allow_safe_elements' => true === $value ? $config->allowSafeElements() : $config,
                'allowed_link_schemes' => $config->allowLinkSchemes(self::strings($value)),
                'allow_relative_links' => $config->allowRelativeLinks(true === $value),
                'allowed_media_schemes' => $config->allowMediaSchemes(self::strings($value)),
                'allow_relative_medias' => $config->allowRelativeMedias(true === $value),
                'with_attribute_sanitizers' => array_reduce(
                    self::strings($value),
                    static function (HtmlSanitizerConfig $config, string $class): HtmlSanitizerConfig {
                        $sanitizer = new $class();
                        Assert::isInstanceOf($sanitizer, AttributeSanitizerInterface::class);

                        return $config->withAttributeSanitizer($sanitizer);
                    },
                    $config,
                ),
                'max_input_length' => $config->withMaxInputLength(is_int($value) ? $value : 0),
                default => throw new \LogicException(sprintf('Map the sanitizer option "%s" here, the way FrameworkBundle does', $option)),
            };
        }

        return new HtmlSanitizer($config);
    }

    /**
     * @return list<string>
     */
    private static function strings(mixed $value): array
    {
        Assert::isList($value);
        Assert::allString($value);

        return $value;
    }
}
