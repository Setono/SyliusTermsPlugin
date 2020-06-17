<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Generator;

use Behat\Transliterator\Transliterator;
use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Webmozart\Assert\Assert;

final class TermSlugGenerator implements TermSlugGeneratorInterface
{
    public function generate(TermsInterface $term, ?string $locale = null): string
    {
        $name = $term->getTranslation($locale)->getName();

        Assert::notEmpty($name, 'Cannot generate slug without a name.');

        return $this->transliterate($name);
    }

    private function transliterate(string $string): string
    {
        // Manually replacing apostrophes since Transliterator started removing them at v1.2.
        return Transliterator::transliterate(str_replace('\'', '-', $string));
    }
}
