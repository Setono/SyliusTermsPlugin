<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Generator;

use Symfony\Component\String\Slugger\SluggerInterface;

final readonly class SlugGenerator implements SlugGeneratorInterface
{
    public function __construct(private SluggerInterface $slugger)
    {
    }

    public function generate(string $name, string $localeCode): string
    {
        return $this->slugger->slug($name, '-', $localeCode)->lower()->toString();
    }
}
