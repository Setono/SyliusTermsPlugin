<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Pdf;

final readonly class InlinedHtml
{
    /**
     * @param string $html the HTML with every downloaded image as a data: URI, and every image that couldn't be
     *                     downloaded replaced by its alt text
     * @param array<string, string> $failedImages why each image that couldn't be downloaded failed, by its URL
     */
    public function __construct(
        public string $html,
        public array $failedImages = [],
    ) {
    }

    public function isComplete(): bool
    {
        return [] === $this->failedImages;
    }
}
