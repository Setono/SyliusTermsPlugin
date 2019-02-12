<?php

namespace spec\Setono\SyliusTermsPlugin\Provider;

use Setono\SyliusTermsPlugin\Provider\TermsProvider;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Setono\SyliusTermsPlugin\Repository\TermsRepositoryInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;

class TermsProviderSpec extends ObjectBehavior
{
    public function let(
        TermsRepositoryInterface $termsRepository,
        ChannelContextInterface $channelContext,
        ChannelInterface $channel,
        LocaleContextInterface $localeContext
    ): void {
        $locale = 'en_US';

        $channelContext->getChannel()->willReturn($channel);
        $localeContext->getLocaleCode()->willReturn($locale);

        $termsRepository->findByChannelAndLocale($channel, $locale)->willReturn([]);

        $this->beConstructedWith($termsRepository, $channelContext, $localeContext);
    }

    public function it_is_initializable(): void
    {
        $this->shouldHaveType(TermsProvider::class);
    }

    public function it_returns_an_array(): void
    {
        $this->getTerms()->shouldBeArray();
    }
}
