<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Provider;

use Setono\SyliusTermsPlugin\Repository\TermsRepositoryInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;

final class TermsProvider implements TermsProviderInterface
{
    public function __construct(
        private readonly TermsRepositoryInterface $termsRepository,
        private readonly ChannelContextInterface $channelContext,
        private readonly LocaleContextInterface $localeContext,
    ) {
    }

    public function getTerms(string $form): array
    {
        return $this->termsRepository->findByFormAndChannelAndLocale(
            $form,
            $this->channelContext->getChannel(),
            $this->localeContext->getLocaleCode(),
        );
    }
}
