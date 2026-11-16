<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use Setono\SyliusTermsPlugin\Renderer\ContentRendererInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

final class ContentSanitizerTest extends KernelTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        restore_exception_handler();
    }

    #[Test]
    public function the_content_sanitizer_is_registered(): void
    {
        $sanitizer = self::getContainer()->get('html_sanitizer.sanitizer.setono_sylius_terms.content');

        self::assertInstanceOf(HtmlSanitizerInterface::class, $sanitizer);
        self::assertSame(
            '<h2 id="content-payment">Payment</h2><p>Pay by card</p>',
            $sanitizer->sanitize('<h2 id="content-payment">Payment</h2><p id="intro" class="lead" onclick="alert(1)">Pay by card</p><script>alert(1)</script>'),
        );
    }

    #[Test]
    public function the_content_renderer_sanitises_with_it(): void
    {
        $contentRenderer = self::getContainer()->get(ContentRendererInterface::class);
        self::assertInstanceOf(ContentRendererInterface::class, $contentRenderer);

        self::assertSame(
            "<h2 id=\"content-payment\">Payment<a href=\"#content-payment\" title=\"Permalink\">#</a></h2>\n<p>Pay by <a>card</a></p>\n\n",
            $contentRenderer->render("## Payment\n\nPay by <a href=\"javascript:alert(1)\" style=\"color: red\">card</a>\n\n<script>alert(1)</script>")->html,
        );
    }

    /**
     * The test application allows sms: links in config/packages/html_sanitizer.yaml
     */
    #[Test]
    public function app_config_extends_the_content_sanitizer(): void
    {
        $contentRenderer = self::getContainer()->get(ContentRendererInterface::class);
        self::assertInstanceOf(ContentRendererInterface::class, $contentRenderer);

        self::assertSame(
            "<p><a href=\"sms:&#43;4512345678\">Text us</a> or <a href=\"tel:&#43;4512345678\">call us</a>, not <a>FTP</a></p>\n",
            $contentRenderer->render('[Text us](sms:+4512345678) or [call us](tel:+4512345678), not [FTP](ftp://example.com/terms.pdf)')->html,
        );
    }
}
