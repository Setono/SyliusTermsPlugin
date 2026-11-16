<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit\Provider;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusTermsPlugin\Lookup\FormTermsLookupInterface;
use Setono\SyliusTermsPlugin\Model\Terms;
use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Setono\SyliusTermsPlugin\Model\TermsVersion;
use Setono\SyliusTermsPlugin\Model\TermsVersionTranslation;
use Setono\SyliusTermsPlugin\Model\TermsVersionTranslationInterface;
use Setono\SyliusTermsPlugin\Provider\TermsProvider;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Model\Channel;
use Sylius\Component\Locale\Context\LocaleContextInterface;

final class TermsProviderTest extends TestCase
{
    use ProphecyTrait;

    #[Test]
    public function it_returns_the_terms_the_lookup_finds_in_the_current_channel_and_locale(): void
    {
        $channel = new Channel();
        $termsAndConditions = new Terms();
        $privacyPolicy = new Terms();

        $channelContext = $this->prophesize(ChannelContextInterface::class);
        $channelContext->getChannel()->willReturn($channel);

        $localeContext = $this->prophesize(LocaleContextInterface::class);
        $localeContext->getLocaleCode()->willReturn('de_DE');

        $lookup = $this->prophesize(FormTermsLookupInterface::class);
        $lookup->lookup('checkout_complete', $channel, 'de_DE')->willReturn([
            self::inForce($termsAndConditions),
            self::inForce($privacyPolicy),
        ]);

        $provider = new TermsProvider($lookup->reveal(), $channelContext->reveal(), $localeContext->reveal());

        self::assertSame([$termsAndConditions, $privacyPolicy], $provider->getTerms('checkout_complete'));
    }

    private static function inForce(TermsInterface $terms): TermsVersionTranslationInterface
    {
        $version = new TermsVersion();
        $terms->addVersion($version);

        $translation = new TermsVersionTranslation();
        $translation->setLocale('de_DE');
        $version->addTranslation($translation);

        return $translation;
    }
}
