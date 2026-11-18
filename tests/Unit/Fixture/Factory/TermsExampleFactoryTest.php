<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit\Fixture\Factory;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusTermsPlugin\Fixture\Factory\TermsExampleFactory;
use Setono\SyliusTermsPlugin\Model\Terms;
use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Setono\SyliusTermsPlugin\Repository\TermsRepositoryInterface;
use Sylius\Component\Channel\Model\Channel;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Locale\Model\Locale;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException;

final class TermsExampleFactoryTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<TermsRepositoryInterface> */
    private ObjectProphecy $termsRepository;

    /** @var ObjectProphecy<ChannelRepositoryInterface> */
    private ObjectProphecy $channelRepository;

    protected function setUp(): void
    {
        $this->termsRepository = $this->prophesize(TermsRepositoryInterface::class);
        $this->termsRepository->findOneBy(Argument::any())->willReturn(null);

        $this->channelRepository = $this->prophesize(ChannelRepositoryInterface::class);
    }

    #[Test]
    public function it_resolves_the_options(): void
    {
        $fashionWeb = self::channel('FASHION_WEB');
        $this->channelRepository->findOneBy(['code' => 'FASHION_WEB'])->willReturn($fashionWeb);

        $terms = $this->createFactory()->create([
            'code' => 'privacy_policy',
            'name' => 'Privacy Policy',
            'slug' => 'privacy-policy',
            'label' => 'I have read the [link:privacy policy]',
            'content' => "## What we collect\n\nYour name.",
            'enabled' => false,
            'required' => false,
            'position' => 3,
            'forms' => ['registration', 'contact'],
            'channels' => ['FASHION_WEB'],
            'locales' => ['en_US'],
        ]);

        self::assertSame('privacy_policy', $terms->getCode());
        self::assertFalse($terms->isEnabled());
        self::assertFalse($terms->isRequired());
        self::assertSame(3, $terms->getPosition());
        self::assertSame(['registration', 'contact'], $terms->getForms());
        self::assertSame([$fashionWeb], $terms->getChannels()->toArray());
        self::assertSame(['en_US'], $terms->getTranslations()->getKeys());

        $translation = $terms->getTranslation('en_US');
        self::assertSame('Privacy Policy', $translation->getName());
        self::assertSame('privacy-policy', $translation->getSlug());
        self::assertSame('I have read the [link:privacy policy]', $translation->getLabel());
        self::assertSame("## What we collect\n\nYour name.", $translation->getContent());
    }

    #[Test]
    public function it_places_the_terms_on_no_form_by_default(): void
    {
        self::assertSame([], $this->createFactory()->create(['code' => 'eula', 'channels' => []])->getForms());
    }

    #[Test]
    public function it_makes_the_terms_enabled_and_required_by_default(): void
    {
        $terms = $this->createFactory()->create(['code' => 'eula', 'channels' => []]);

        self::assertTrue($terms->isEnabled());
        self::assertTrue($terms->isRequired());
    }

    #[Test]
    public function it_puts_the_terms_last_when_the_position_is_blank(): void
    {
        self::assertSame(-1, $this->createFactory()->create(['code' => 'eula', 'channels' => []])->getPosition());
    }

    #[Test]
    public function it_fails_on_an_unconfigured_form(): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->expectExceptionMessage('The Form(s) "newsletter" are not configured. The configured Forms are "checkout_complete", "registration", "contact".');

        $this->createFactory()->create(['code' => 'eula', 'channels' => [], 'forms' => ['checkout_complete', 'newsletter']]);
    }

    #[Test]
    public function it_puts_the_terms_in_every_channel_by_default(): void
    {
        $channels = [self::channel('A'), self::channel('B'), self::channel('C'), self::channel('D')];
        $this->channelRepository->findAll()->willReturn($channels);

        $terms = $this->createFactory()->create(['code' => 'eula']);

        self::assertSame($channels, $terms->getChannels()->toArray());
    }

    #[Test]
    public function it_translates_the_terms_into_every_locale_by_default(): void
    {
        $terms = $this->createFactory()->create(['code' => 'eula', 'channels' => [], 'name' => 'EULA']);

        self::assertSame(['en_US', 'de_DE', 'fr_FR'], $terms->getTranslations()->getKeys());
        self::assertSame('EULA', $terms->getTranslation('fr_FR')->getName());
    }

    #[Test]
    public function translations_override_the_defaults_per_locale_and_can_add_a_locale(): void
    {
        $terms = $this->createFactory()->create([
            'code' => 'terms_and_conditions',
            'channels' => [],
            'locales' => ['en_US', 'de_DE'],
            'name' => 'Terms and Conditions',
            'label' => 'I accept the [link:terms and conditions]',
            'content' => '## Ordering',
            'translations' => [
                'de_DE' => ['name' => 'Allgemeine Geschäftsbedingungen'],
                'fr_FR' => ['name' => 'Conditions générales', 'slug' => 'conditions-generales'],
            ],
        ]);

        self::assertSame(['en_US', 'de_DE', 'fr_FR'], $terms->getTranslations()->getKeys());

        self::assertSame('Terms-and-Conditions', $terms->getTranslation('en_US')->getSlug());

        $german = $terms->getTranslation('de_DE');
        self::assertSame('Allgemeine Geschäftsbedingungen', $german->getName());
        self::assertSame('Allgemeine-Geschaftsbedingungen', $german->getSlug());
        self::assertSame('I accept the [link:terms and conditions]', $german->getLabel());
        self::assertSame('## Ordering', $german->getContent());

        self::assertSame('conditions-generales', $terms->getTranslation('fr_FR')->getSlug());
    }

    #[Test]
    public function it_fails_on_an_unknown_translation_option(): void
    {
        $this->expectException(UndefinedOptionsException::class);

        $this->createFactory()->create(['code' => 'eula', 'channels' => [], 'translations' => ['de_DE' => ['title' => 'EULA']]]);
    }

    #[Test]
    public function it_fakes_a_label_with_a_link_placeholder_and_content_with_two_sections(): void
    {
        $terms = $this->createFactory()->create(['code' => 'eula', 'channels' => [], 'locales' => ['en_US'], 'name' => 'EULA']);
        $translation = $terms->getTranslation('en_US');

        self::assertSame('I have read and accept the [link:EULA]', $translation->getLabel());
        self::assertMatchesRegularExpression('/\A## .+\n\n.+\n\n## .+\n\n.+\z/', (string) $translation->getContent());
    }

    #[Test]
    public function it_derives_the_code_from_a_faked_name(): void
    {
        $terms = $this->createFactory()->create(['channels' => [], 'locales' => ['en_US']]);

        self::assertMatchesRegularExpression('/\A[A-Z][a-z]+( [a-z]+){2}\z/', (string) $terms->getTranslation('en_US')->getName());
        self::assertNotEmpty($terms->getCode());
    }

    #[Test]
    public function it_updates_the_terms_with_the_same_code(): void
    {
        $existing = new Terms();
        $existing->setCode('eula');
        $this->termsRepository->findOneBy(['code' => 'eula'])->willReturn($existing);

        self::assertSame($existing, $this->createFactory()->create(['code' => 'eula', 'channels' => [], 'required' => false]));
        self::assertFalse($existing->isRequired());
    }

    private function createFactory(): TermsExampleFactory
    {
        $termsFactory = $this->prophesize(FactoryInterface::class);
        $termsFactory->createNew()->will(static fn (): TermsInterface => new Terms());

        $localeRepository = $this->prophesize(RepositoryInterface::class);
        $localeRepository->findAll()->willReturn([self::locale('en_US'), self::locale('de_DE'), self::locale('fr_FR'), new Locale()]);

        return new TermsExampleFactory(
            $termsFactory->reveal(),
            $this->termsRepository->reveal(),
            $localeRepository->reveal(),
            $this->channelRepository->reveal(),
            ['checkout_complete' => [], 'registration' => [], 'contact' => []],
        );
    }

    private static function locale(string $code): Locale
    {
        $locale = new Locale();
        $locale->setCode($code);

        return $locale;
    }

    private static function channel(string $code): Channel
    {
        $channel = new Channel();
        $channel->setCode($code);

        return $channel;
    }
}
