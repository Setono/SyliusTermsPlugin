<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Model;

/**
 * The rule both setContent()s store Content by, and the check for a new Terms version compares it by, so they can't
 * drift apart. TermsTranslationInterface::setContent() describes it
 *
 * @internal
 */
final class ContentNormalizer
{
    public static function normalize(?string $content): ?string
    {
        if (null === $content) {
            return null;
        }

        $content = str_replace(["\r\n", "\r"], "\n", $content);

        // The blank lines before the first non-blank line, which keeps its indentation, then all whitespace at the end
        $content = rtrim((string) preg_replace('/\A(?:[ \t]*\n)+/', '', $content), " \t\n");

        return '' === $content ? null : $content;
    }
}
