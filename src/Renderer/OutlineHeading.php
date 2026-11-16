<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Renderer;

/**
 * A ## heading in rendered Content
 */
final readonly class OutlineHeading
{
    /**
     * @param string $id the heading's id attribute, so a link to it is "#" followed by the id
     * @param string $text the heading as plain text, not escaped
     */
    public function __construct(
        public string $id,
        public string $text,
    ) {
    }
}
