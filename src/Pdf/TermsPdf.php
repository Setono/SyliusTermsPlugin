<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Pdf;

final readonly class TermsPdf
{
    /**
     * @param string $content the PDF bytes
     * @param string $fileName the Terms version's name, slugified, plus its number, such as handelsbetingelser-v3.pdf
     * @param bool $complete false when an image couldn't be downloaded and its alt text is shown instead. An incomplete
     *                       PDF isn't cached, so the next call renders it again
     */
    public function __construct(
        public string $content,
        public string $fileName,
        public bool $complete = true,
    ) {
    }
}
