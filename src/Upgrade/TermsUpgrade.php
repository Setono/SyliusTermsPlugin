<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Upgrade;

use Setono\SyliusTermsPlugin\Model\TermsInterface;

/**
 * What upgrading from 2.x does to one Terms. Part of the upgrade from 2.x, which 4.0 removes
 */
final readonly class TermsUpgrade
{
    /**
     * @param int|null $position the position a Terms from 2.x gets along with version 1. Null for a Terms that already
     *                           has a version, which keeps its versions and its position
     * @param array<string, string> $mappedForms each stored form type class that gets mapped, with the Form code it becomes
     * @param list<string> $forms the Forms the Terms is placed on after the upgrade
     * @param list<string> $unconfiguredForms the ones among $forms that aren't configured
     * @param array<string, non-empty-list<ContentCase>> $contentCases the ways the Content of a Terms from 2.x may
     *                                                                 render differently, by locale
     */
    public function __construct(
        public TermsInterface $terms,
        public ?int $position,
        public array $mappedForms,
        public array $forms,
        public array $unconfiguredForms,
        public array $contentCases,
    ) {
    }

    /**
     * A Terms with no version is 2.x data, since 3.0 creates version 1 along with a Terms
     */
    public function isFrom2x(): bool
    {
        return null !== $this->position;
    }

    public function hasWrites(): bool
    {
        return $this->isFrom2x() || [] !== $this->mappedForms;
    }
}
