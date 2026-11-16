<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Twig;

use Setono\SyliusTermsPlugin\Renderer\ContentRendererInterface;
use Twig\Extension\RuntimeExtensionInterface;

final readonly class ContentRuntime implements RuntimeExtensionInterface
{
    public function __construct(private ContentRendererInterface $contentRenderer)
    {
    }

    /**
     * Returns the Content as sanitised HTML. The outline isn't returned here, see ContentRendererInterface
     */
    public function render(?string $content): string
    {
        return $this->contentRenderer->render($content ?? '')->html;
    }
}
