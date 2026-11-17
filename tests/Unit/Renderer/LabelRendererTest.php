<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit\Renderer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusTermsPlugin\Model\Terms;
use Setono\SyliusTermsPlugin\Model\TermsTranslation;
use Setono\SyliusTermsPlugin\Model\TermsVersion;
use Setono\SyliusTermsPlugin\Model\TermsVersionTranslation;
use Setono\SyliusTermsPlugin\Renderer\LabelRenderer;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Webmozart\Assert\InvalidArgumentException;

final class LabelRendererTest extends TestCase
{
    use ProphecyTrait;

    #[Test]
    #[DataProvider('provideLabels')]
    public function it_renders_the_label_of_the_version(string $label, ?string $content, string $expected): void
    {
        $urlGenerator = $this->prophesize(UrlGeneratorInterface::class);
        $urlGenerator->generate('setono_sylius_terms_shop_terms_show', ['slug' => 'vilkaar'])->willReturn('/da_DK/terms/vilkaar');

        $renderer = new LabelRenderer($urlGenerator->reveal());

        self::assertSame($expected, $renderer->render(self::createTranslation($label, $content)));
    }

    /**
     * @return \Generator<string, array{label: string, content: string|null, expected: string}>
     */
    public static function provideLabels(): \Generator
    {
        yield 'a label without a Link placeholder links as a whole' => [
            'label' => 'Accept terms',
            'content' => 'content',
            'expected' => '<a href="/da_DK/terms/vilkaar" target="_blank">Accept terms</a>',
        ];

        yield 'the Link placeholder becomes the link' => [
            'label' => 'Accept the [link:terms] now',
            'content' => 'content',
            'expected' => 'Accept the <a href="/da_DK/terms/vilkaar" target="_blank">terms</a> now',
        ];

        yield 'HTML is escaped, and the Link placeholder still becomes the link' => [
            'label' => 'Accept the <b>[link:"terms" & <i>conditions</i>]</b>',
            'content' => 'content',
            'expected' => 'Accept the &lt;b&gt;<a href="/da_DK/terms/vilkaar" target="_blank">&quot;terms&quot; &amp; &lt;i&gt;conditions&lt;/i&gt;</a>&lt;/b&gt;',
        ];

        yield 'HTML is escaped in a label that links as a whole' => [
            'label' => '<script>alert(1)</script>',
            'content' => 'content',
            'expected' => '<a href="/da_DK/terms/vilkaar" target="_blank">&lt;script&gt;alert(1)&lt;/script&gt;</a>',
        ];

        yield 'without content, HTML is escaped and nothing links' => [
            'label' => 'Accept <b>terms</b> & <script>alert(1)</script>',
            'content' => null,
            'expected' => 'Accept &lt;b&gt;terms&lt;/b&gt; &amp; &lt;script&gt;alert(1)&lt;/script&gt;',
        ];

        yield 'without content, the Link placeholder leaves its link text' => [
            'label' => 'Accept the [link:<b>terms</b>]',
            'content' => '',
            'expected' => 'Accept the &lt;b&gt;terms&lt;/b&gt;',
        ];
    }

    #[Test]
    public function it_escapes_the_link(): void
    {
        $urlGenerator = $this->prophesize(UrlGeneratorInterface::class);
        $urlGenerator->generate(Argument::cetera())->willReturn('/terms/vilkaar?a=1&b="2"');

        $renderer = new LabelRenderer($urlGenerator->reveal());

        self::assertSame(
            '<a href="/terms/vilkaar?a=1&amp;b=&quot;2&quot;" target="_blank">Accept terms</a>',
            $renderer->render(self::createTranslation('Accept terms', 'content')),
        );
    }

    #[Test]
    public function it_links_to_the_terms_page_by_the_slug_in_the_locale_of_the_version_translation(): void
    {
        $urlGenerator = $this->prophesize(UrlGeneratorInterface::class);
        $urlGenerator->generate('setono_sylius_terms_shop_terms_show', ['slug' => 'agb'])->willReturn('/de_DE/terms/agb');

        $translation = self::createTranslation('Ich akzeptiere die [link:AGB]', 'Inhalt', 'de_DE');
        $translation->getVersion()->getTerms()?->getTranslation('de_DE')->setSlug('agb');

        $renderer = new LabelRenderer($urlGenerator->reveal());

        self::assertSame('Ich akzeptiere die <a href="/de_DE/terms/agb" target="_blank">AGB</a>', $renderer->render($translation));
    }

    #[Test]
    public function it_requires_a_label(): void
    {
        $translation = self::createTranslation('Accept terms', 'content');
        $translation->setLabel(null);

        $renderer = new LabelRenderer($this->prophesize(UrlGeneratorInterface::class)->reveal());

        $this->expectException(InvalidArgumentException::class);

        $renderer->render($translation);
    }

    #[Test]
    public function it_requires_a_slug_to_link_to_the_terms_page(): void
    {
        $translation = self::createTranslation('Accept terms', 'content');
        $translation->getVersion()->getTerms()?->getTranslation('da_DK')->setSlug(null);

        $renderer = new LabelRenderer($this->prophesize(UrlGeneratorInterface::class)->reveal());

        $this->expectException(InvalidArgumentException::class);

        $renderer->render($translation);
    }

    #[Test]
    public function it_requires_the_version_to_belong_to_a_terms(): void
    {
        $version = new TermsVersion();
        $translation = new TermsVersionTranslation();
        $translation->setLocale('da_DK');
        $translation->setLabel('Accept terms');
        $translation->setContent('content');
        $version->addTranslation($translation);

        $renderer = new LabelRenderer($this->prophesize(UrlGeneratorInterface::class)->reveal());

        $this->expectException(InvalidArgumentException::class);

        $renderer->render($translation);
    }

    /**
     * A version translation of a Terms whose translation in the same locale has the slug "vilkaar"
     */
    private static function createTranslation(string $label, ?string $content, string $locale = 'da_DK'): TermsVersionTranslation
    {
        $terms = new Terms();

        $termsTranslation = new TermsTranslation();
        $termsTranslation->setLocale($locale);
        $termsTranslation->setSlug('vilkaar');
        $terms->addTranslation($termsTranslation);

        $version = new TermsVersion();
        $terms->addVersion($version);

        $translation = new TermsVersionTranslation();
        $translation->setLocale($locale);
        $translation->setLabel($label);
        $translation->setContent($content);
        $version->addTranslation($translation);

        return $translation;
    }
}
