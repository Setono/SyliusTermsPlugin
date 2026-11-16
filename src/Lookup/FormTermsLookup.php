<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Lookup;

use Psr\Clock\ClockInterface;
use Setono\SyliusTermsPlugin\Repository\TermsRepositoryInterface;
use Setono\SyliusTermsPlugin\Repository\TermsVersionRepositoryInterface;
use Sylius\Component\Channel\Model\ChannelInterface;

final readonly class FormTermsLookup implements FormTermsLookupInterface
{
    public function __construct(
        private TermsRepositoryInterface $termsRepository,
        private TermsVersionRepositoryInterface $termsVersionRepository,
        private ClockInterface $clock,
    ) {
    }

    public function lookup(string $form, ChannelInterface $channel, string $localeCode, ?\DateTimeInterface $at = null): array
    {
        $at ??= $this->clock->now();

        $inForce = [];

        // One query per Terms, of which a Form holds a handful
        foreach ($this->termsRepository->findByFormAndChannel($form, $channel) as $terms) {
            $translation = $this->termsVersionRepository->findOneInForce($terms, $at, $localeCode);
            if (null !== $translation) {
                $inForce[] = $translation;
            }
        }

        return $inForce;
    }
}
