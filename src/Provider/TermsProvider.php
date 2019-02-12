<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Provider;

use Setono\SyliusTermsPlugin\Doctrine\ORM\TermsRepositoryInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;

final class TermsProvider implements TermsProviderInterface
{
    /**
     * @var TermsRepositoryInterface
     */
    private $termsRepository;

    /**
     * @var ChannelContextInterface
     */
    private $channelContext;

    /**
     * @var LocaleContextInterface
     */
    private $localeContext;

    /**
     * @param TermsRepositoryInterface $termsRepository
     * @param ChannelContextInterface $channelContext
     * @param LocaleContextInterface $localeContext
     */
    public function __construct(TermsRepositoryInterface $termsRepository, ChannelContextInterface $channelContext, LocaleContextInterface $localeContext)
    {
        $this->termsRepository = $termsRepository;
        $this->channelContext = $channelContext;
        $this->localeContext = $localeContext;
    }

    public function getTerms(): array
    {
        return $this->termsRepository->findByChannel(
            $this->channelContext->getChannel(),
            $this->localeContext->getLocaleCode()
        );
    }
}
