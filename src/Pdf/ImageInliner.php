<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Pdf;

use Masterminds\HTML5;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final readonly class ImageInliner implements ImageInlinerInterface
{
    /**
     * @param float $timeout the most seconds one image may take to download
     * @param int $maxBytes the largest image, in bytes, that is downloaded
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private float $timeout = 5.0,
        private int $maxBytes = 5 * 1024 * 1024,
    ) {
    }

    public function inline(string $html): InlinedHtml
    {
        $html5 = new HTML5();
        $document = $html5->loadHTML($html);

        // Copied to an array, because replacing an image removes it from the live node list
        $images = [...$document->getElementsByTagName('img')];

        /** @var array<string, ResponseInterface> $responses */
        $responses = [];
        $failedImages = [];

        // Every request starts before the first response is read, so the images download in parallel
        foreach ($images as $image) {
            $url = trim($image->getAttribute('src'));

            if (isset($responses[$url]) || isset($failedImages[$url]) || 1 !== preg_match('#^https?://#i', $url)) {
                continue;
            }

            try {
                $responses[$url] = $this->request($url);
            } catch (ExceptionInterface $e) {
                // Such as a malformed URL
                $failedImages[$url] = $e->getMessage();
            }
        }

        if ([] === $responses && [] === $failedImages) {
            return new InlinedHtml($html);
        }

        $dataUris = [];

        foreach ($responses as $url => $response) {
            try {
                $dataUris[$url] = $this->toDataUri($response);
            } catch (ExceptionInterface|\UnexpectedValueException $e) {
                $failedImages[$url] = $e->getMessage();
            }
        }

        foreach ($images as $image) {
            $url = trim($image->getAttribute('src'));

            if (isset($dataUris[$url])) {
                $image->setAttribute('src', $dataUris[$url]);
            } elseif (isset($failedImages[$url])) {
                $image->replaceWith($document->createTextNode($image->getAttribute('alt')));
            }
        }

        return new InlinedHtml($html5->saveHTML($document), $failedImages);
    }

    private function request(string $url): ResponseInterface
    {
        return $this->httpClient->request('GET', $url, [
            'timeout' => $this->timeout,
            'max_duration' => $this->timeout,
            // Throwing here cancels the download. The size is the Content-Length, or 0 when the response doesn't send it
            'on_progress' => function (int $downloaded, int $size): void {
                if ($downloaded > $this->maxBytes || $size > $this->maxBytes) {
                    throw new \UnexpectedValueException(sprintf('The image is larger than %d bytes', $this->maxBytes));
                }
            },
        ]);
    }

    /**
     * @throws ExceptionInterface on a timeout, a transport error, an error status or an image over the size cap
     * @throws \UnexpectedValueException when the response isn't an image
     */
    private function toDataUri(ResponseInterface $response): string
    {
        $contentType = strtolower(trim(explode(';', $response->getHeaders()['content-type'][0] ?? '')[0]));

        if (!str_starts_with($contentType, 'image/')) {
            $response->cancel();

            throw new \UnexpectedValueException(sprintf('The response is not an image, but "%s"', $contentType));
        }

        return sprintf('data:%s;base64,%s', $contentType, base64_encode($response->getContent()));
    }
}
