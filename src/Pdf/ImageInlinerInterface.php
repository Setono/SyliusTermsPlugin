<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Pdf;

/**
 * Downloads the images of an HTML document and inlines them as data: URIs, so the PDF engine never fetches anything
 */
interface ImageInlinerInterface
{
    /**
     * Only http and https images are downloaded. Images with any other src, such as a relative URL or a data: URI,
     * are left as they are. An image that can't be downloaded is replaced by its alt text
     */
    public function inline(string $html): InlinedHtml;
}
