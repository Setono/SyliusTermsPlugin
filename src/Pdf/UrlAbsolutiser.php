<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Pdf;

use Masterminds\HTML5;
use Sylius\Component\Channel\Model\ChannelInterface;

final readonly class UrlAbsolutiser implements UrlAbsolutiserInterface
{
    /** The elements whose URL attribute is made absolute */
    private const URL_ATTRIBUTES = [
        'a' => 'href',
        'img' => 'src',
    ];

    /**
     * @param bool $unsecuredUrls true makes the URLs http instead of https, like Sylius' own email links (%sylius.unsecured_urls%)
     */
    public function __construct(private bool $unsecuredUrls = false)
    {
    }

    public function absolutise(string $html, ChannelInterface $channel): string
    {
        $hostname = $channel->getHostname();
        if (null === $hostname || '' === $hostname) {
            return $html;
        }

        $scheme = $this->unsecuredUrls ? 'http' : 'https';
        $baseUrl = sprintf('%s://%s', $scheme, $hostname);

        $html5 = new HTML5(['disable_html_ns' => true]);
        $document = $html5->loadHTML($html);

        foreach (self::URL_ATTRIBUTES as $tagName => $attribute) {
            foreach ($document->getElementsByTagName($tagName) as $element) {
                if ($element->hasAttribute($attribute)) {
                    $element->setAttribute($attribute, self::resolve(trim($element->getAttribute($attribute)), $scheme, $baseUrl));
                }
            }
        }

        return $html5->saveHTML($document);
    }

    private static function resolve(string $url, string $scheme, string $baseUrl): string
    {
        // A URL with a scheme, such as https: or mailto:, and a link within the document stay as they are
        if ('' === $url || str_starts_with($url, '#') || 1 === preg_match('/^[a-z][a-z\d+.-]*:/i', $url)) {
            return $url;
        }

        // A network-path reference, such as //cdn.example.com/logo.png, only lacks the scheme
        if (str_starts_with($url, '//')) {
            return $scheme . ':' . $url;
        }

        if (str_starts_with($url, '/')) {
            return $baseUrl . $url;
        }

        // The PDF has no address of its own, so a path such as images/logo.png or ../logo.png resolves from the root
        return $baseUrl . '/' . (string) preg_replace('#^(\.\.?/)+#', '', $url);
    }
}
