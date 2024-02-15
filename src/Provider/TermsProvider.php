<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Provider;

use Setono\SyliusTermsPlugin\Repository\TermsRepositoryInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;

final class TermsProvider implements TermsProviderInterface
{
    /** @var TermsRepositoryInterface */
    private $termsRepository;

    /** @var ChannelContextInterface */
    private $channelContext;

    public function __construct(TermsRepositoryInterface $termsRepository, ChannelContextInterface $channelContext)
    {
        $this->termsRepository = $termsRepository;
        $this->channelContext = $channelContext;
    }

    public function getTerms(): array
    {
        return $this->termsRepository->findByChannel(
            $this->channelContext->getChannel(),
        );
    }
}
