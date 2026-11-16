<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit\Twig;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusTermsPlugin\Renderer\ContentRendererInterface;
use Setono\SyliusTermsPlugin\Renderer\OutlineHeading;
use Setono\SyliusTermsPlugin\Renderer\RenderedContent;
use Setono\SyliusTermsPlugin\Twig\ContentRuntime;
use Setono\SyliusTermsPlugin\Twig\TermsExtension;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

final class ContentRuntimeTest extends TestCase
{
    use ProphecyTrait;

    #[Test]
    public function it_returns_the_rendered_html_only(): void
    {
        $contentRenderer = $this->prophesize(ContentRendererInterface::class);
        $contentRenderer->render('## Payment')->willReturn(new RenderedContent(
            '<h2 id="content-payment">Payment</h2>',
            [new OutlineHeading('content-payment', 'Payment')],
        ));

        self::assertSame('<h2 id="content-payment">Payment</h2>', (new ContentRuntime($contentRenderer->reveal()))->render('## Payment'));
    }

    #[Test]
    public function it_renders_null_as_empty_content(): void
    {
        $contentRenderer = $this->prophesize(ContentRendererInterface::class);
        $contentRenderer->render('')->willReturn(new RenderedContent(''));

        self::assertSame('', (new ContentRuntime($contentRenderer->reveal()))->render(null));
    }

    #[Test]
    public function the_terms_content_filter_prints_the_html_unescaped(): void
    {
        $contentRenderer = $this->prophesize(ContentRendererInterface::class);
        $contentRenderer->render('**VAT** included')->willReturn(new RenderedContent('<p><strong>VAT</strong> included</p>'));

        $twig = new Environment(new ArrayLoader(['content' => '{{ content|terms_content }}']));
        $twig->addExtension(new TermsExtension());
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([
            ContentRuntime::class => static fn (): ContentRuntime => new ContentRuntime($contentRenderer->reveal()),
        ]));

        self::assertSame('<p><strong>VAT</strong> included</p>', $twig->render('content', ['content' => '**VAT** included']));
    }
}
