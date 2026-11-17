<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Application\HttpClient;

use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * The test environment's http_client answers through this factory (framework.http_client.mock_response_factory), so
 * no test touches the network. A test adds the responses it needs. Every other URL gets a 404
 */
final class MockResponseFactory
{
    /** @var array<string, MockResponse> */
    private array $responses = [];

    /** @var list<string> */
    private array $requestedUrls = [];

    public function add(string $url, MockResponse $response): void
    {
        $this->responses[$url] = $response;
    }

    /**
     * @return list<string>
     */
    public function getRequestedUrls(): array
    {
        return $this->requestedUrls;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function __invoke(string $method, string $url, array $options = []): ResponseInterface
    {
        $this->requestedUrls[] = $url;

        return $this->responses[$url] ?? new MockResponse('', ['http_code' => 404]);
    }
}
