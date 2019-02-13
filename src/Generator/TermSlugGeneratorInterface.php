<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Generator;

use Setono\SyliusTermsPlugin\Model\TermsInterface;

interface TermSlugGeneratorInterface
{
    /**
     * @param TermsInterface $term
     * @param string|null $locale
     * @return string
     */
    public function generate(TermsInterface $term, ?string $locale = null): string;
}
