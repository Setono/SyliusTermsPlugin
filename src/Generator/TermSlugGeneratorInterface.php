<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Generator;

use Setono\SyliusTermsPlugin\Model\TermsInterface;

interface TermSlugGeneratorInterface
{
    public function generate(TermsInterface $term, ?string $locale = null): string;
}
