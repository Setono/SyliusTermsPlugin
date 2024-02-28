<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Renderer;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Setono\SyliusTermsPlugin\Renderer\LabelRenderer;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class LabelRendererTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     *
     * @dataProvider getTerms
     */
    public function it_renders(string $label, string $slug, ?string $content, string $expected): void
    {
        $urlGenerator = $this->prophesize(UrlGeneratorInterface::class);
        $urlGenerator->generate('setono_sylius_terms_shop_show_terms', ['slug' => $slug])->willReturn('https://example.com/terms/' . $slug);

        $terms = $this->prophesize(TermsInterface::class);
        $terms->getLabel()->willReturn($label);
        $terms->getSlug()->willReturn($slug);
        $terms->getContent()->willReturn($content);

        $renderer = new LabelRenderer($urlGenerator->reveal());

        self::assertSame($expected, $renderer->render($terms->reveal()));
    }

    /**
     * @return \Generator<array-key, array{label: string, slug: string, content: string|null, expected: string}>
     */
    public function getTerms(): \Generator
    {
        yield [
            'label' => 'Accept terms',
            'slug' => 'slug',
            'content' => 'content',
            'expected' => '<a href="https://example.com/terms/slug" target="_blank">Accept terms</a>',
        ];

        yield [
            'label' => 'Accept [link:terms]',
            'slug' => 'slug',
            'content' => 'content',
            'expected' => 'Accept <a href="https://example.com/terms/slug" target="_blank">terms</a>',
        ];

        yield [
            'label' => 'Accept terms',
            'slug' => 'slug',
            'content' => null,
            'expected' => 'Accept terms',
        ];

        yield [
            'label' => 'Accept terms',
            'slug' => 'slug',
            'content' => '',
            'expected' => 'Accept terms',
        ];
    }
}
