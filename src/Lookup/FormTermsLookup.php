<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Lookup;

use Psr\Clock\ClockInterface;
use Setono\SyliusTermsPlugin\Repository\TermsRepositoryInterface;
use Setono\SyliusTermsPlugin\Repository\TermsVersionRepositoryInterface;
use Sylius\Component\Channel\Model\ChannelInterface;

final readonly class FormTermsLookup implements FormTermsLookupInterface
{
    /**
     * @param array<string, mixed> $forms the enabled Forms, keyed by code (the setono_sylius_terms.forms parameter)
     */
    public function __construct(
        private TermsRepositoryInterface $termsRepository,
        private TermsVersionRepositoryInterface $termsVersionRepository,
        private ClockInterface $clock,
        private array $forms,
    ) {
    }

    public function lookup(string $form, ChannelInterface $channel, string $localeCode, ?\DateTimeInterface $at = null): array
    {
        // Terms keep the codes of Forms that have since been switched off or removed from the config
        if (!isset($this->forms[$form])) {
            return [];
        }

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
