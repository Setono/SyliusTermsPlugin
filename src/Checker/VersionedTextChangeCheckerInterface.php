<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Checker;

use Setono\SyliusTermsPlugin\Model\TermsInterface;

/**
 * Versioned text is a Terms' name, label and content in every locale. Everything else on a Terms (slug, code,
 * channels, Forms, enabled) is not versioned
 */
interface VersionedTextChangeCheckerInterface
{
    /**
     * Returns true if the Terms' versioned text differs from its latest version, including when a translation was added
     * or removed, or if the Terms has no version yet
     */
    public function hasChanged(TermsInterface $terms): bool;
}
