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
    public function it_renders(string $label, string $slug, string $expected): void
    {
        $urlGenerator = $this->prophesize(UrlGeneratorInterface::class);
        $urlGenerator->generate('setono_sylius_terms_shop_show_terms', ['slug' => $slug])->willReturn('https://example.com/terms/' . $slug);

        $terms = $this->prophesize(TermsInterface::class);
        $terms->getLabel()->willReturn($label);
        $terms->getSlug()->willReturn($slug);

        $renderer = new LabelRenderer($urlGenerator->reveal());

        self::assertSame($expected, $renderer->render($terms->reveal()));
    }

    /**
     * @return \Generator<array-key, array{label: string, slug: string, expected: string}>
     */
    public function getTerms(): \Generator
    {
        yield [
            'label' => 'Accept terms',
            'slug' => 'slug',
            'expected' => '<a href="https://example.com/terms/slug" target="_blank">Accept terms</a>',
        ];

        yield [
            'label' => 'Accept [link:terms]',
            'slug' => 'slug',
            'expected' => 'Accept <a href="https://example.com/terms/slug" target="_blank">terms</a>',
        ];
    }
}
