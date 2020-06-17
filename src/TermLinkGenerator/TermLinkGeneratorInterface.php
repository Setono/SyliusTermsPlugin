<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\TermLinkGenerator;

use Setono\SyliusTermsPlugin\Model\TermsInterface;

interface TermLinkGeneratorInterface
{
    public function isApplicable(TermsInterface $terms): bool;

    public function generate(TermsInterface $terms, ?string $locale = null): string;
}
