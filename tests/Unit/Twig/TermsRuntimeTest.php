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
use Sylius\Component\Core\Model\ChannelInterface as CoreChannelInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Locale\Model\Locale;
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
        $urlGenerator
            ->generate('setono_sylius_terms_shop_terms_show', ['slug' => 'terms-and-conditions', '_locale' => 'en_US'])
            ->willReturn('/en_US/terms/terms-and-conditions')
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

    #[Test]
    public function the_channel_path_is_the_terms_page_in_the_channels_default_locale(): void
    {
        $channel = $this->createChannel('en_US');
        $translation = self::createTranslation('en_US');
        $this->termsPageLookup->lookupByCode('terms_and_conditions', $channel, 'en_US')->willReturn($translation);

        // The current locale is de_DE, so the path names the channel's locale
        self::assertSame('/en_US/terms/terms-and-conditions', $this->runtime->channelPath(self::getTerms($translation), $channel));
    }

    #[Test]
    public function the_channel_path_is_null_when_the_terms_has_no_page_in_the_channel(): void
    {
        $channel = $this->createChannel('en_US');
        $translation = self::createTranslation('en_US');
        $this->termsPageLookup->lookupByCode('terms_and_conditions', $channel, 'en_US')->willReturn(null);

        self::assertNull($this->runtime->channelPath(self::getTerms($translation), $channel));
    }

    #[Test]
    public function the_channel_path_is_null_without_a_code_or_a_default_locale(): void
    {
        // The lookup isn't asked: any call would fail as unexpected
        self::assertNull($this->runtime->channelPath(self::getTerms(self::createTranslation('en_US')), $this->createChannel(null)));
        self::assertNull($this->runtime->channelPath(new Terms(), $this->createChannel('en_US')));
    }

    #[Test]
    public function terms_channel_path_prints_the_channel_path(): void
    {
        $channel = $this->createChannel('en_US');
        $translation = self::createTranslation('en_US');
        $this->termsPageLookup->lookupByCode('terms_and_conditions', $channel, 'en_US')->willReturn($translation);

        self::assertSame(
            '<a href="/en_US/terms/terms-and-conditions">',
            $this->render('<a href="{{ terms_channel_path(terms, channel) }}">', context: ['terms' => self::getTerms($translation), 'channel' => $channel]),
        );
    }

    #[Test]
    public function it_fails_when_the_terms_has_no_slug_in_the_locale_of_the_page(): void
    {
        $translation = self::createTranslation();
        self::getTerms($translation)->getTranslation('de_DE')->setSlug(null);
        $this->termsPageLookup->lookupByCode('no_slug', $this->channel, 'de_DE')->willReturn($translation);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected a value other than null.');

        $this->runtime->path('no_slug');
    }

    #[Test]
    public function it_fails_when_the_version_of_the_page_belongs_to_no_terms(): void
    {
        $translation = new TermsVersionTranslation();
        $translation->setLocale('de_DE');
        (new TermsVersion())->addTranslation($translation);
        $this->termsPageLookup->lookupByCode('no_terms', $this->channel, 'de_DE')->willReturn($translation);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected a value other than null.');

        $this->runtime->path('no_terms');
    }

    /**
     * Renders the template with the plugin's templates, after the override, if given
     *
     * @param array<string, mixed> $context
     */
    private function render(string $template, ?LoaderInterface $override = null, array $context = []): string
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

        return $twig->render('template', $context);
    }

    /**
     * Version 3 of the Terms in the locale. The Terms' own name has changed since, which the link doesn't show, but its
     * slug is the one the page has now
     */
    private static function createTranslation(string $localeCode = 'de_DE'): TermsVersionTranslation
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
        $translation->setLocale($localeCode);
        $translation->setName('AGB & Datenschutz');
        $version->addTranslation($translation);

        return $translation;
    }

    private static function getTerms(TermsVersionTranslation $translation): Terms
    {
        $terms = $translation->getVersion()->getTerms();
        self::assertInstanceOf(Terms::class, $terms);

        return $terms;
    }

    private function createChannel(?string $defaultLocaleCode): CoreChannelInterface
    {
        $defaultLocale = null;
        if (null !== $defaultLocaleCode) {
            $defaultLocale = new Locale();
            $defaultLocale->setCode($defaultLocaleCode);
        }

        $channel = $this->prophesize(CoreChannelInterface::class);
        $channel->getDefaultLocale()->willReturn($defaultLocale);

        return $channel->reveal();
    }
}
