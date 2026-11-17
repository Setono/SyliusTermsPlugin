<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Pdf;

use Setono\SyliusTermsPlugin\Model\TermsVersionInterface;
use Sylius\Component\Channel\Model\ChannelInterface;

/**
 * Generates the Terms PDF of one Terms version, in one locale and for one channel. The PDF holds no order data, so one
 * PDF serves every order that accepted the version
 */
interface TermsPdfGeneratorInterface
{
    /**
     * The channel's hostname makes relative URLs in the PDF absolute.
     *
     * An image that can't be downloaded is replaced by its alt text, an error naming the image URL is logged, and the
     * returned PDF is marked incomplete
     *
     * @param array<string, mixed> $logContext added to the context of the errors logged for images that couldn't be
     *                                         downloaded, such as the number of the order the PDF is for
     *
     * @throws \InvalidArgumentException if the version has no translation in the locale
     */
    public function generate(
        TermsVersionInterface $version,
        string $localeCode,
        ChannelInterface $channel,
        array $logContext = [],
    ): TermsPdf;
}
