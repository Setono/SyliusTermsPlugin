<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Renderer;

use Setono\SyliusTermsPlugin\Model\TermsInterface;

interface LabelRendererInterface
{
    /**
     * Will render the label of the terms as a HTML <a href="...">...</a> tag
     */
    public function render(TermsInterface $terms): string;
}
