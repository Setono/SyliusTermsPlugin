<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Renderer;

use Setono\SyliusTermsPlugin\Model\TermsVersionTranslationInterface;

interface LabelRendererInterface
{
    /**
     * Renders the Label of a Terms version in one locale as HTML for the Terms checkbox. The Label is plain text and
     * is escaped. Its Link placeholder ([link:Link text]) becomes a link to the Terms page, and a Label without one
     * becomes a link as a whole. Without Content there is nothing to link to, so the Label is text only, and a Link
     * placeholder leaves just its link text
     */
    public function render(TermsVersionTranslationInterface $translation): string;
}
