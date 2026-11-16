<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Renderer;

final readonly class RenderedContent
{
    /**
     * @param string $html sanitised HTML, safe to print
     * @param list<OutlineHeading> $outline the ## headings, in order
     */
    public function __construct(
        public string $html,
        public array $outline = [],
    ) {
    }
}
