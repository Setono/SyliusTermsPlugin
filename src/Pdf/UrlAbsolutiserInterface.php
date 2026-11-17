<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Pdf;

use Sylius\Component\Channel\Model\ChannelInterface;

interface UrlAbsolutiserInterface
{
    /**
     * Makes the relative link (a href) and image (img src) URLs in the HTML document absolute against the channel's
     * hostname. Absolute URLs and links within the document (#...) are left as they are. Without a hostname, the
     * HTML is returned unchanged
     */
    public function absolutise(string $html, ChannelInterface $channel): string;
}
