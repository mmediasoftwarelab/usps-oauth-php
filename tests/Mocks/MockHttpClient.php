<?php

declare(strict_types=1);

namespace MMedia\USPS\Tests\Mocks;

use MMedia\USPS\Http\HttpClientInterface;
use MMedia\USPS\Http\HttpResponse;

/**
 * Mock HTTP Client for Testing
 * 
 * Allows testing without making real API calls
 */
class MockHttpClient implements HttpClientInterface
{
    /** @var array<string, array{statusCode: int, body: string}> */
    private array $responses = [];

    /** @var array<int, array{url: string, method: string, headers: array, body: ?string}> */
    private array $requestHistory = [];

    /**
     * Add a mocked response for a specific URL pattern
     */
    public function addResponse(string $urlPattern, int $statusCode, string $body): void
    {
        $this->responses[$urlPattern] = [
            'statusCode' => $statusCode,
            'body' => $body,
        ];
    }

    /**
     * Add a JSON response
     *
     * @param array<string, mixed> $data
     */
    public function addJsonResponse(string $urlPattern, int $statusCode, array $data): void
    {
        $this->addResponse($urlPattern, $statusCode, json_encode($data));
    }

    public function request(
        string $url,
        string $method = 'GET',
        array $headers = [],
        ?string $body = null
    ): HttpResponse {
        // Store request for assertions
        $this->requestHistory[] = [
            'url' => $url,
            'method' => $method,
            'headers' => $headers,
            'body' => $body,
        ];

        // Find matching response
        foreach ($this->responses as $pattern => $response) {
            if (str_contains($url, $pattern)) {
                return new HttpResponse(
                    statusCode: $response['statusCode'],
                    body: $response['body'],
                    headers: []
                );
            }
        }

        // Default 404 response if no match
        return new HttpResponse(
            statusCode: 404,
            body: json_encode(['error' => ['message' => 'Not found']]),
            headers: []
        );
    }

    /**
     * Get all recorded requests
     *
     * @return array<int, array{url: string, method: string, headers: array, body: ?string}>
     */
    public function getRequestHistory(): array
    {
        return $this->requestHistory;
    }

    /**
     * Get the last request made
     *
     * @return array{url: string, method: string, headers: array, body: ?string}|null
     */
    public function getLastRequest(): ?array
    {
        return end($this->requestHistory) ?: null;
    }

    /**
     * Clear all mocked responses and request history
     */
    public function reset(): void
    {
        $this->responses = [];
        $this->requestHistory = [];
    }

    /**
     * Assert that a request was made to a URL containing the pattern
     */
    public function assertRequestMade(string $urlPattern): bool
    {
        foreach ($this->requestHistory as $request) {
            if (str_contains($request['url'], $urlPattern)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Count requests made to URLs containing the pattern
     */
    public function countRequests(string $urlPattern): int
    {
        $count = 0;
        foreach ($this->requestHistory as $request) {
            if (str_contains($request['url'], $urlPattern)) {
                $count++;
            }
        }
        return $count;
    }
}
