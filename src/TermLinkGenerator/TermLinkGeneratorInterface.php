<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\TermLinkGenerator;

use Setono\SyliusTermsPlugin\Model\TermsInterface;

interface TermLinkGeneratorInterface
{
    /**
     * @param TermsInterface $terms
     * @return bool
     */
    public function isApplicable(TermsInterface $terms): bool;

    /**
     * @param TermsInterface $terms
     * @param string|null $locale
     * @return string
     */
    public function generate(TermsInterface $terms, ?string $locale = null): string;
}
