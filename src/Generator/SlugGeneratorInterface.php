<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Generator;

interface SlugGeneratorInterface
{
    /**
     * Returns the slug for a Terms' name in a locale, such as privacy-policy for "Privacy Policy" in en_US. The locale
     * decides the transliteration, so "Datenschutzerklärung" in de_DE becomes datenschutzerklaerung
     */
    public function generate(string $name, string $localeCode): string;
}
