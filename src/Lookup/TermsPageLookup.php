<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Lookup;

use Psr\Clock\ClockInterface;
use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Setono\SyliusTermsPlugin\Model\TermsVersionTranslationInterface;
use Setono\SyliusTermsPlugin\Repository\TermsRepositoryInterface;
use Setono\SyliusTermsPlugin\Repository\TermsVersionRepositoryInterface;
use Sylius\Component\Channel\Model\ChannelInterface;

final readonly class TermsPageLookup implements TermsPageLookupInterface
{
    public function __construct(
        private TermsRepositoryInterface $termsRepository,
        private TermsVersionRepositoryInterface $termsVersionRepository,
        private ClockInterface $clock,
    ) {
    }

    public function lookupBySlug(string $slug, ChannelInterface $channel, string $localeCode): ?TermsVersionTranslationInterface
    {
        return $this->findInForce(
            $this->termsRepository->findOneByChannelAndLocaleAndSlug($channel, $localeCode, $slug),
            $localeCode,
        );
    }

    public function lookupByCode(string $code, ChannelInterface $channel, string $localeCode): ?TermsVersionTranslationInterface
    {
        return $this->findInForce(
            $this->termsRepository->findOneByChannelAndLocaleAndCode($channel, $localeCode, $code),
            $localeCode,
        );
    }

    /**
     * The repository has already left out a Terms that is disabled, not in the channel or not translated into the locale
     */
    private function findInForce(?TermsInterface $terms, string $localeCode): ?TermsVersionTranslationInterface
    {
        if (null === $terms) {
            return null;
        }

        $translation = $this->termsVersionRepository->findOneInForce($terms, $this->clock->now(), $localeCode);

        // Without Content the page would be empty, the case where LabelRenderer doesn't link the Label either
        $content = $translation?->getContent();
        if (null === $content || '' === $content) {
            return null;
        }

        return $translation;
    }
}
