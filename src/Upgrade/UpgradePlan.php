<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Upgrade;

/**
 * What upgrading from 2.x does to every Terms. Part of the upgrade from 2.x, which 4.0 removes
 */
final readonly class UpgradePlan
{
    /**
     * @param \DateTimeImmutable $inForceFrom the moment the version 1 of each Terms from 2.x is in force from
     * @param list<TermsUpgrade> $termsUpgrades one per Terms, in id order
     */
    public function __construct(
        public \DateTimeImmutable $inForceFrom,
        public array $termsUpgrades,
    ) {
    }

    /**
     * @return list<TermsUpgrade>
     */
    public function getTermsUpgradesFrom2x(): array
    {
        return array_values(array_filter(
            $this->termsUpgrades,
            static fn (TermsUpgrade $termsUpgrade): bool => $termsUpgrade->isFrom2x(),
        ));
    }

    public function hasWrites(): bool
    {
        foreach ($this->termsUpgrades as $termsUpgrade) {
            if ($termsUpgrade->hasWrites()) {
                return true;
            }
        }

        return false;
    }
}
