<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit\Twig;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusTermsPlugin\Lookup\TermsPageLookupInterface;
use Setono\SyliusTermsPlugin\Model\Terms;
use Setono\SyliusTermsPlugin\Model\TermsTranslation;
use Setono\SyliusTermsPlugin\Model\TermsVersion;
use Setono\SyliusTermsPlugin\Model\TermsVersionTranslation;
use Setono\SyliusTermsPlugin\Twig\TermsExtension;
use Setono\SyliusTermsPlugin\Twig\TermsRuntime;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\Loader\LoaderInterface;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

/**
 * The current channel is FASHION_WEB and the locale de_DE. Whether a Terms page exists is the Terms page lookup's
 * call: an unknown code, a disabled Terms, a Terms in another channel and no version in force all come back as null,
 * see Tests\Functional\TermsPageLookupTest
 */
final class TermsRuntimeTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<TermsPageLookupInterface> */
    private ObjectProphecy $termsPageLookup;

    private ChannelInterface $channel;

    private TermsRuntime $runtime;

    protected function setUp(): void
    {
        $this->channel = $this->prophesize(ChannelInterface::class)->reveal();

        $channelContext = $this->prophesize(ChannelContextInterface::class);
        $channelContext->getChannel()->willReturn($this->channel);

        $localeContext = $this->prophesize(LocaleContextInterface::class);
        $localeContext->getLocaleCode()->willReturn('de_DE');

        $urlGenerator = $this->prophesize(UrlGeneratorInterface::class);
        $urlGenerator
            ->generate('setono_sylius_terms_shop_terms_show', ['slug' => 'allgemeine-geschaeftsbedingungen'])
            ->willReturn('/de_DE/terms/allgemeine-geschaeftsbedingungen')
        ;

        $this->termsPageLookup = $this->prophesize(TermsPageLookupInterface::class);
        $this->termsPageLookup->lookupByCode('unknown', $this->channel, 'de_DE')->willReturn(null);
        $this->termsPageLookup->lookupByCode('terms_and_conditions', $this->channel, 'de_DE')->willReturn(self::createTranslation());

        $this->runtime = new TermsRuntime(
            $this->termsPageLookup->reveal(),
            $channelContext->reveal(),
            $localeContext->reveal(),
            $urlGenerator->reveal(),
        );
    }

    #[Test]
    public function the_path_is_the_terms_page_by_the_current_slug_in_the_locale(): void
    {
        self::assertSame('/de_DE/terms/allgemeine-geschaeftsbedingungen', $this->runtime->path('terms_and_conditions'));
    }

    #[Test]
    public function the_link_text_is_the_name_of_the_version_in_force(): void
    {
        self::assertSame(
            '<a href="/de_DE/terms/allgemeine-geschaeftsbedingungen">AGB &amp; Datenschutz</a>',
            $this->render("{{ terms_link('terms_and_conditions') }}"),
        );
    }

    #[Test]
    public function the_link_has_the_given_attributes_escaped(): void
    {
        self::assertSame(
            '<a href="/de_DE/terms/allgemeine-geschaeftsbedingungen" class="nav-link" title="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;" onclick&#x3D;&quot;alert&#x28;1&#x29;&quot;="">AGB &amp; Datenschutz</a>',
            $this->render(<<<'TWIG'
                {{ terms_link('terms_and_conditions', {class: 'nav-link', title: '"><script>alert(1)</script>', 'onclick="alert(1)"': ''}) }}
                TWIG),
        );
    }

    #[Test]
    public function the_link_ignores_a_given_href_and_points_at_the_terms_page(): void
    {
        self::assertSame(
            '<a href="/de_DE/terms/allgemeine-geschaeftsbedingungen" class="nav-link">AGB &amp; Datenschutz</a>',
            $this->render("{{ terms_link('terms_and_conditions', {href: '/elsewhere', class: 'nav-link', HREF: '/elsewhere'}) }}"),
        );
    }

    #[Test]
    public function the_link_renders_from_the_overridable_link_template(): void
    {
        $override = new ArrayLoader([
            TermsRuntime::LINK_TEMPLATE => '<a class="legal" href="{{ path }}">{{ translation.name|upper }} ({{ terms.code }})</a>',
        ]);

        self::assertSame(
            'Read the <a class="legal" href="/de_DE/terms/allgemeine-geschaeftsbedingungen">AGB &amp; DATENSCHUTZ (terms_and_conditions)</a>.',
            $this->render("Read the {{ terms_link('terms_and_conditions') }}.", $override),
        );
    }

    #[Test]
    public function both_return_nothing_when_there_is_no_terms_page(): void
    {
        self::assertNull($this->runtime->path('unknown'));
        self::assertSame('[]', $this->render("[{{ terms_link('unknown', {class: 'nav-link'}) }}]"));
        self::assertSame('[]', $this->render("[{{ terms_path('unknown') }}]"));
    }

    #[Test]
    public function terms_path_prints_the_path(): void
    {
        self::assertSame(
            '<a href="/de_DE/terms/allgemeine-geschaeftsbedingungen">',
            $this->render("<a href=\"{{ terms_path('terms_and_conditions') }}\">"),
        );
    }

    /**
     * Renders the template with the plugin's templates, after the override, if given
     */
    private function render(string $template, ?LoaderInterface $override = null): string
    {
        $plugin = new FilesystemLoader();
        $plugin->addPath(dirname(__DIR__, 3) . '/templates', 'SetonoSyliusTermsPlugin');

        $twig = new Environment(new ChainLoader(array_filter([
            $override,
            new ArrayLoader(['template' => $template]),
            $plugin,
        ])), ['strict_variables' => true]);
        $twig->addExtension(new TermsExtension());
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([
            TermsRuntime::class => fn (): TermsRuntime => $this->runtime,
        ]));

        return $twig->render('template');
    }

    /**
     * Version 3 of the Terms in de_DE. The Terms' own name has changed since, which the link doesn't show, but its
     * slug is the one the page has now
     */
    private static function createTranslation(): TermsVersionTranslation
    {
        $terms = new Terms();
        $terms->setCode('terms_and_conditions');

        foreach (['en_US' => 'terms-and-conditions', 'de_DE' => 'allgemeine-geschaeftsbedingungen'] as $locale => $slug) {
            $termsTranslation = new TermsTranslation();
            $termsTranslation->setLocale($locale);
            $termsTranslation->setName('Allgemeine Geschäftsbedingungen, geändert');
            $termsTranslation->setSlug($slug);
            $terms->addTranslation($termsTranslation);
        }

        $version = new TermsVersion();
        $version->setNumber(3);
        $terms->addVersion($version);

        $translation = new TermsVersionTranslation();
        $translation->setLocale('de_DE');
        $translation->setName('AGB & Datenschutz');
        $version->addTranslation($translation);

        return $translation;
    }
}
