<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Provider;

use Setono\SyliusTermsPlugin\Doctrine\ORM\TermsRepositoryInterface;
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
            $this->channelContext->getChannel()
        );
    }

    public function getEnabledTermsForCompleteForm(): array
    {
        return $this->termsRepository->findByChannelAndEnabledForCompleteForm(
            $this->channelContext->getChannel()
        );
    }

    public function getEnabledTermsForCustomerRegistrationForm(): array
    {
        return $this->termsRepository->findByChannelAndEnabledForCustomerRegistrationForm(
            $this->channelContext->getChannel()
        );
    }

    public function getEnabledTermsForFooterTemplate(): array
    {
        return $this->termsRepository->findByChannelAndEnabledForFooterTemplate(
            $this->channelContext->getChannel()
        );
    }
}
