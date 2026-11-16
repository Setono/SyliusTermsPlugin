<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Sanitizer;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Keeps the id attribute on h1–h6, where the heading permalinks and the outline point, and drops it from every other element
 */
final class HeadingIdAttributeSanitizer implements AttributeSanitizerInterface
{
    private const HEADINGS = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];

    public function getSupportedElements(): ?array
    {
        return null;
    }

    /**
     * @return list<string>
     */
    public function getSupportedAttributes(): array
    {
        return ['id'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        return in_array($element, self::HEADINGS, true) ? $value : null;
    }
}
