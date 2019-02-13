<?php

namespace spec\Setono\SyliusTermsPlugin\Provider;

use Setono\SyliusTermsPlugin\Doctrine\ORM\TermsRepositoryInterface;
use Setono\SyliusTermsPlugin\Provider\TermsProvider;
use PhpSpec\ObjectBehavior;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Model\ChannelInterface;

class TermsProviderSpec extends ObjectBehavior
{
    public function let(
        TermsRepositoryInterface $termsRepository,
        ChannelContextInterface $channelContext,
        ChannelInterface $channel
    ): void {
        $channelContext->getChannel()->willReturn($channel);

        $termsRepository->findByChannel($channel)->willReturn([]);

        $this->beConstructedWith($termsRepository, $channelContext);
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
