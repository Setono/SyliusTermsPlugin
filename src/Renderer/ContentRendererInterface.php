<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Renderer;

/**
 * Renders Content, which is Markdown that allows raw HTML, to HTML that is safe to print.
 *
 * Every surface that shows Content goes through this service. An implementation that replaces the default one has to
 * sanitise its output itself
 */
interface ContentRendererInterface
{
    /**
     * Returns the sanitised HTML and the outline of its ## headings, both from the same parse
     */
    public function render(string $content): RenderedContent;
}
